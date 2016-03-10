<?php
/**
 * Created by PhpStorm.
 * User: work
 * Date: 3/9/16
 * Time: 7:36 PM
 */

namespace AppBundle\Controller\Api;

use AppBundle\Entity\CartProduct;
use AppBundle\Entity\Cart;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;


class ShoppingCartController extends Controller
{

    /**
     * @Route("/api/get-products", name="api-get-products")
     */
    public function jsonGetProducts()
    {
        $em = $this->getDoctrine()->getManager();
        $products = $em->getRepository('AppBundle:Part')->findAll();
        $line_numbers = array();
        $line_numbers[] = '';
        foreach($products as $item) {
            $json_products[$item->getId()] = array(
                'stock_number' => $item->getStockNumber(),
                'description' => $item->getDescription(),
                'id' => $item->getId(),
                'require_return' => $item->getRequireReturn(),
                'category' => $item->getPartCategory()->getName(),
                'part_name_cononical' => $item->getPartCategory()->getNameCononical(),
                'quantity' => 0,
                'line_numbers' => $line_numbers
            );
        }
        return JsonResponse::create($json_products);
    }

    /**
     * @Route("/api/load-cart", name="api-load-cart")
     */
    public function loadCartAction()
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
        return $this->sumCart($cart);
    }

    /**
     * @Route("/api/add-cart-item", name="api-add-item")
     */
    public function addCartItemAction(Request $request)
    {
        $user = $this->getUser();
        $id = $request->request->get('id');
        $em = $this->getDoctrine()->getManager();

        if(!$cart = $em->getRepository('AppBundle:Cart')->findOneBy(array('user' => $user, 'submitted' => 0))) {
            $cart = new Cart();
            $cart->setUser($user);
            $cart->setOffice($user->getOffice());
            $cart->setDate(date_create(date("Y-m-d H:i:s")));
            $em->persist($cart);
            $em->flush();
        }
        $part = $em->getRepository('AppBundle:Part')->find($id);
        if(!$product = $em->getRepository('AppBundle:CartProduct')->findOneBy(array('cart' => $cart, 'part' => $part))) {
            $product = new CartProduct();
            $product->setCart($cart);
            $product->setPart($part);
            $product->setStockLocation("ADD THIS");
            $em->persist($product);
            $em->flush();
        }

        $product->setQuantity($product->getQuantity() + 1);
        $em->persist($product);
        $em->flush();

        return $this->sumCart($cart);
    }

    /**
     * @Route("/api/remove-cart-item", name="api-remove-item")
     */
    public function removeCartItemAction(Request $request)
    {
        $user = $this->getUser();
        $id = $request->request->get('id');
        $em = $this->getDoctrine()->getManager();

        $cart = $em->getRepository('AppBundle:Cart')->findOneBy(array('user' => $user, 'submitted' => 0));
        $part = $em->getRepository('AppBundle:Part')->find($id);
        $product = $em->getRepository('AppBundle:CartProduct')->findOneBy(array('cart' => $cart, 'part' => $part));

        if($product->getQuantity() == 1)
            $em->remove($product);
        else {
            $product->setQuantity($product->getQuantity() - 1);
            $em->persist($product);
        }
        $em->flush();

        return $this->sumCart($cart);
    }


    public function sumCart($cart)
    {
        $count = 0;
        $json_cart = array();
        $line_numbers[] = '';
        foreach($cart->getCartProducts() as $product) {
            $json_cart[] = array(
                'stock_number' => $product->getPart()->getStockNumber(),
                'description' => $product->getPart()->getDescription(),
                'id' => $product->getPart()->getId(),
                'require_return' => $product->getPart()->getRequireReturn(),
                'category' => $product->getPart()->getPartCategory()->getName(),
                'part_name_cononical' => $product->getPart()->getPartCategory()->getNameCononical(),
                'quantity' => $product->getQuantity(),
                'line_numbers' => $line_numbers
            );
            $count += $product->getQuantity();
        }
        return JsonResponse::create(array('cart' => $json_cart, 'num_items' => $count));
    }
}