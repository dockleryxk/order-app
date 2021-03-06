<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Entity\CartProduct;
use AppBundle\Entity\CartProductLineNumber;
use AppBundle\Entity\Cart;

class CartController extends Controller
{
    /**
     * @Route("/", name="user_home")
     */
    public function userHomeAction()
    {
        $em = $this->getDoctrine()->getManager();
        $products = $em->getRepository('AppBundle:Part')->findAll();
        $shipping = $em->getRepository('AppBundle:ShippingMethod')->findAll();
        $categories = $em->getRepository('AppBundle:PartCategory')->findAll();

//        foreach($products as $product) {
//            $num = substr($product->getStockNumber(), 0, -4);
//
//            $product->setPictureUrl('/USDA_Parts/'. $num . '.jpg');
//            $em->persist($product);
//        }
        $em->flush();

        return $this->render('AppBundle:Cart:home.html.twig', array(
            'products' => $products,
            'categories' => $categories,
            'shipping' => $shipping,
        ));
    }

    /**
     * @Route("/review-order", name="review_order")
     */
    public function reviewOrderAction()
    {
        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();

        if(!$cart = $em->getRepository('AppBundle:Cart')->findOneBy(array('user' => $user, 'submitted' => 0))) {
            $cart = new Cart();
            $cart->setUser($user);
            $cart->setOffice($user->getOffice());
            $cart->setDate(date_create(date("Y-m-d H:i:s")));
            $em->persist($cart);
            $em->flush();
        }
        $products = $em->getRepository('AppBundle:Part')->findAll();
        $categories = $em->getRepository('AppBundle:PartCategory')->findAll();

        return $this->render('AppBundle:Cart:review-order.html.twig', array(
            'products' => $products,
            'categories' => $categories,
            'cart_id' => $cart->getid(),
            'shipping' => ($cart->getShippingMethod() != null ? $cart->getShippingMethod()->getName() : 'none')
        ));
    }

    /**
     * @Route("/review-order/submit/{cart_id}", name="submit_cart")
     */
    public function submitOrderAction(Request $request, $cart_id)
    {
        $em = $this->getDoctrine()->getManager();
        $cart = $em->getRepository('AppBundle:Cart')->find($cart_id);
        $cart->setSubmitted(1);
        $cart->setSubmitDate(date_create(date("Y-m-d H:i:s")));

        $user = $this->getUser();
        $num = str_pad($cart_id, 4, '0', STR_PAD_LEFT);
        $officeId = '00';
        $year = date('y');

        if($user->getOffice()) {
            $officeId = $user->getOffice()->getOfficeNumber();
        }
        $orderNum = $officeId . $year . $num;

        $cart->setOrderNumber($orderNum);

        $em->persist($cart);
        $em->flush();
        $this->addFlash('notice', 'Order submitted successfully.');

        $hour = date('H');

        if($hour < 15) {
            $shipDate = date_create(date("Y-m-d"));
        }
        else {
            $shipDate = date_create(date("Y-m-d"));
            $shipDate->modify('+1 day');
        }

        if($shipDate->format('D') == 'Sat') {
            $shipDate->modify('+1 day');
            $shipDate->modify('+1 day');
        }
        elseif($shipDate->format('D') == 'Sun') {
            $shipDate->modify('+1 day');
        }

        $this->addFlash('notice', 'Your order will ship on ' . $shipDate->format('m/d/Y'));

        /*
         * SEND EMAILS TO ALL ADMIN NOTIFYING THEM THAT AN ORDER WAS SUBMITTED.
         *
         */
        $from = 'utus-orders@gmail.com';
        $to = 'Knoxville_USDA@uster.com';

        try {
            $email_service = $this->get('email_service');
            $email_service->sendEmail(array(
                    'subject' => $cart->getOffice()->getName() . " Order # " . $cart->getOrderNumber(),
                    'from' => $from,
                    'to' => $to,
                    'body' => $this->renderView("AppBundle:Email:order_submit_notification.html.twig",
                        array(
                            'cart' => $cart
                        )
                    )
                )
            );
        } catch(\Exception $e) {
            $this->addFlash('error', 'Success email failed to send: ' . $e->getMessage());
            return $this->redirectToRoute('view_all_orders');
        }



        return $this->redirectToRoute('view_all_orders');
    }

    /**
     * @Route("/view-all-orders", name="view_all_orders")
     */
    public function viewAllOrdersAction()
    {
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $submitted = $em->getRepository('AppBundle:Cart')->findBy(array('user' => $user, 'submitted' => 1, 'approved' => 0));

        $sql = "select c.id, c.submit_date, sum(p.ship_quantity) shipped
	from cart c
		left join cart_products p
			on p.cart_id = c.id
	where c.approved = 1
	AND c.submitted = 1
	and c.user_id = :user_id
	group by c.id;";
        $stmt = $em->getConnection()->prepare($sql);
        $params['user_id'] = $user->getId();
        $stmt->execute($params);
        $numShipped = $stmt->fetchAll();

        return $this->render('AppBundle:Cart:view-all-orders.html.twig',
            array(
                'submitted' => $submitted,
                'approved' => $numShipped
            )
        );
    }

    /**
     * @Route("/view-order/{order_id}", name="view_past_order")
     */
    public function viePastOrderAction($order_id)
    {
        $em = $this->getDoctrine()->getManager();
        $products = $em->getRepository('AppBundle:Part')->findAll();
        $categories = $em->getRepository('AppBundle:PartCategory')->findAll();

        return $this->render('AppBundle:Cart:view-past-order.html.twig', array(
            'products' => $products,
            'categories' => $categories,
            'order_id' => $order_id
        ));
    }
}
