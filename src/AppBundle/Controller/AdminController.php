<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Invitation;
use AppBundle\Entity\User;
use AppBundle\Repository\OfficeRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Form\UserType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;


class AdminController extends Controller
{
    /**
     * @Route("/admin", name="admin_home")
     */
    public function adminHomeAction()
    {
        $em = $this->getDoctrine()->getManager();
        $sql = "select c.id, c.order_number, c.submit_date, sum(p.quantity) items, o.name as office_name, CONCAT_WS(\" \", c.requester_first_name, c.requester_last_name) as submitted_by
	from cart c
		left join cart_products p
			on p.cart_id = c.id
		left join users u
			on c.user_id = u.id
		left join offices o
			on c.office_id = o.id
	where c.approved = 0
	AND c.submitted = 1
	group by c.id";
        $stmt = $em->getConnection()->prepare($sql);
        $stmt->execute();
        $submitted = $stmt->fetchAll();

        $sql = "select c.id, c.submit_date, c.approve_date, c.order_number, sum(p.ship_quantity) shipped, o.name as office_name, CONCAT_WS(\" \", c.requester_first_name, c.requester_last_name) as submitted_by, CONCAT_WS(\" \", u2.first_name, u2.last_name) as approved_by
	from cart c
		left join cart_products p
			on p.cart_id = c.id
		left join users u
			on c.user_id = u.id
		left join users u2
			on c.approved_by_id = u2.id
		left join offices o
			on c.office_id = o.id
	where c.approved = 1
	AND c.submitted = 1
	group by c.id
	order by c.submit_date DESC";
        $stmt = $em->getConnection()->prepare($sql);
        $stmt->execute();
        $approved = $stmt->fetchAll();

        $sql = "select count(*) as num from users";
        $stmt = $em->getConnection()->prepare($sql);
        $stmt->execute();
        $numUsers = $stmt->fetch();

        $sql = "select count(*) as num from parts";
        $stmt = $em->getConnection()->prepare($sql);
        $stmt->execute();
        $numParts = $stmt->fetch();

        $sql = "select count(*) as num from offices";
        $stmt = $em->getConnection()->prepare($sql);
        $stmt->execute();
        $numOffices = $stmt->fetch();

        $sql = "select count(*) as num from cart where approved = 1";
        $stmt = $em->getConnection()->prepare($sql);
        $stmt->execute();
        $numApproved = $stmt->fetch();

        $sql = "select count(*) as num from cart where approved = 0 AND submitted = 1";
        $stmt = $em->getConnection()->prepare($sql);
        $stmt->execute();
        $numPending = $stmt->fetch();

        if(!$numApproved['num'])
            $numApproved['num'] = 0;

        return $this->render('AppBundle:Admin:home.html.twig', array(
            'submitted' => $submitted,
            'approved' => $approved,
            'num_users' => $numUsers['num'],
            'num_parts' => $numParts['num'],
            'num_offices' => $numOffices['num'],
            'num_approved' => $numApproved['num'],
            'num_pending' => $numPending['num']
        ));
    }

    /**
     * @Route("/admin/add-user", name="send_invitation")
     */
    public function sendInvitationAction(Request $request)
    {
        $invitation = new Invitation();
        $officeRepository = $this->getDoctrine()->getRepository('AppBundle:Office');

        $form = $this->createFormBuilder($invitation)
            ->add('email', EmailType::class)
            ->add('admin', CheckboxType::class, array(
                'label' => 'Give user admin privileges?',
                'required' => false,
            ))
            ->add('office', EntityType::class, array(
                'class' => 'AppBundle:Office',
                'label' => 'Office to assign to?',
                'choice_label' => 'name',
                'query_builder' => function (OfficeRepository $officeRepository) {
                    return $officeRepository->createQueryBuilder('u')->orderBy('u.name', 'ASC');
                }
            ))
            ->getForm();
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getEntityManager();
            $data = $form->getData();

            if($officeId = $data->getOffice()) {
                $office = $officeRepository->find($officeId);
                $invitation->setOffice($office);
            }
            else
                $invitation->setOffice(null);

            $email_service = $this->get('email_service');
            $email_service->sendEmail(array(
                    'subject' => 'Invitation to register for utus-orders.com',
                    'from' => 'utus-orders@gmail.com',
                    'to' => $invitation->getEmail(),
                    'body' => $this->renderView("AppBundle:Email:send_invitation_email.html.twig", array('code' => $invitation->getCode()))
                )
            );

            $invitation->send();
            $em->persist($invitation);
            $em->flush();

            $successMessage = "Invitation to ".$invitation->getEmail()." sent succesfully.";
            $this->addFlash('notice', $successMessage);

            $invitation = new Invitation();
            $form = $this->createFormBuilder($invitation)
                ->add('email', EmailType::class)
                ->add('admin', CheckboxType::class, array(
                    'label' => 'Give user admin privileges?',
                    'required' => false,
                ))
                ->add('office', EntityType::class, array(
                    'class' => 'AppBundle:Office',
                    'label' => 'Office to assign to?',
                    'choice_label' => 'name'
                ))
                ->getForm();

            return $this->render('@App/Security/send_invitation.html.twig', array(
                'form' => $form->createView()
            ));
        }

        return $this->render('@App/Security/send_invitation.html.twig', array(
            'form' => $form->createView()
        ));
    }

    /**
     * @Route("/admin/add-user/sent-invitations", name="all_invitations")
     */
    public function showAllInvitationsAction(Request $request)
    {
        $conn = $this->get('database_connection');
        $invitations = $conn->fetchAll('SELECT * FROM invitation');

        return $this->render('@App/Security/invitations_sent.html.twig', array(
            'invitations' => $invitations
        ));
    }

    /**
     * @Route("/admin/view-users", name="view_users")
     */
    public function viewAllUsersAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $users = $em->getRepository('AppBundle:User')->findAll();

        return $this->render('@App/Admin/view_users.html.twig', array(
            'users' => $users
        ));
    }

    /**
     * @Route("/admin/view-users/edit/{user_id}", name="admin_edit_user")
     */
    public function viewAdminEditUserAction(Request $request, $user_id)
    {
        /** @var $userManager \FOS\UserBundle\Model\UserManagerInterface */
        $userManager = $this->get('fos_user.user_manager');
        $user = $userManager->findUserBy(array('id' => $user_id));

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if($form->isValid()) {
            try {
                $event = new FormEvent($form, $request);
                $userManager->updateUser($user);
                $successMessage = "User information updated succesfully.";
                $this->addFlash('notice', $successMessage);

                return $this->redirectToRoute('view_users');
            } catch(\Exception $e) {
                $this->addFlash('error', 'Error editing user: ' . $e->getMessage() . "\n");
                return $this->render('@App/Admin/admin_edit_user.html.twig', array(
                    'form' => $form->createView(),
                    'user_id' => $user_id
                ));
            }
        }

        return $this->render('@App/Admin/admin_edit_user.html.twig', array(
            'form' => $form->createView(),
            'user_id' => $user_id
        ));
    }

    /**
     * Deletes a User entity.
     *
     * @Route("/admin/view-users/delete/{id}", name="admin_delete_user")
     */
    public function deleteUserAction(Request $request, User $user)
    {
        $userManager = $this->get('fos_user.user_manager');

        try {
            $userManager->deleteUser($user);
            $successMessage = "User removed succesfully.";
            $this->addFlash('notice', $successMessage);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error removing user: ' . $e->getMessage());
            return $this->redirectToRoute('view_users');
        }

        $this->addFlash('notice', 'User deleted successfully.');
        return $this->redirectToRoute('view_users');
    }

    /**
     * @Route("/admin/order/{cart_id}/review", name="admin_order_edit")
     */
    public function viewAdminEditOrderAction(Request $request, $cart_id)
    {
        $em = $this->getDoctrine()->getManager();

        $products = $em->getRepository('AppBundle:Part')->findAll();
        $categories = $em->getRepository('AppBundle:PartCategory')->findAll();
        $stock_location = $em->getRepository('AppBundle:StockLocation')->findAll();
        $part_prefix = $em->getRepository('AppBundle:PartNumberPrefix')->findAll();
        $cart = $em->getRepository('AppBundle:Cart')->find($cart_id);
        if($cart->getApproved()) {
            $cart->setApproved(0);
            $em->persist($cart);
            $em->flush();
        }
        $shipping = $em->getRepository('AppBundle:ShippingMethod')->findAll();
        return $this->render('AppBundle:Admin:review_order.html.twig', array(
            'products' => $products,
            'categories' => $categories,
            'cart_id' => $cart_id,
            'office' => $cart->getOffice(),
            'user' => $cart->getUser(),
            'user_notes' => $cart->getNote(),
            'shipping' => $shipping,
            'stock_location' => $stock_location,
            'part_prefix' => $part_prefix,
            'requested_by' => $cart->getRequesterFirstName() . ' ' . $cart->getRequesterLastName(),
            'cart' => $cart
        ));
    }

    /**
     * @Route("/admin/order/{cart_id}", name="admin_order_approve")
     */
    public function approveOrderAction(Request $request, $cart_id)
    {
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $stock_location = $em->getRepository('AppBundle:StockLocation')->findAll();
        $part_prefix = $em->getRepository('AppBundle:PartNumberPrefix')->findAll();
        $products = $em->getRepository('AppBundle:Part')->findAll();
        $categories = $em->getRepository('AppBundle:PartCategory')->findAll();
        $cart = $em->getRepository('AppBundle:Cart')->find($cart_id);
        $shipping = $em->getRepository('AppBundle:ShippingMethod')->findAll();
        if(!$cart->getApproved()) {
            $this->addFlash('notice', "Order Approved Successfully.");
            /*
             * SEND EMAILS TO EVERYONE HERE
             *
             */
            try {
                //only send the email
                $connection = $em->getConnection();
                $statement = $connection->prepare("select * from office_email where office_id = :id");
                $statement->bindValue('id', $cart->getOffice()->getId());

                $statement->execute();
                $data = $statement->fetchAll();

                foreach($data as $email) {
                    $from = 'utus-orders@gmail.com';
                    $to = $email['email'];

                    $email_service = $this->get('email_service');
                    $email_service->sendEmail(array(
                            'subject' => $cart->getOffice()->getName() . " Order # " . $cart->getOrderNumber() . " has been fulfilled.",
                            'from' => $from,
                            'to' => $to,
                            'body' => $this->renderView("AppBundle:Email:order_approved_notification.html.twig",
                                array(
                                    'cart' => $cart
                                )
                            )
                        )
                    );
                }
            } catch(\Exception $e) {
                $this->addFlash('error', 'Success email failed to send: ' . $e->getMessage());
                return $this->redirectToRoute('view_all_orders');
            }

            $cart->setApproved(1);
            $cart->setApprovedBy($user);
            $cart->setApproveDate(date_create(date("Y-m-d H:i:s")));
        }

        $em->persist($cart);
        $em->flush();

        return $this->render('AppBundle:Admin:approve_order.html.twig', array(
            'products' => $products,
            'categories' => $categories,
            'cart_id' => $cart_id,
            'office' => $cart->getOffice(),
            'user' => $cart->getUser(),
            'user_notes' => $cart->getNote(),
            'shipping' => ($cart->getShippingMethod() != null ? $cart->getShippingMethod()->getName() : "None"),
            'cart' => $cart,
            'requested_by' => $cart->getRequesterFirstName() . ' ' . $cart->getRequesterLastName(),
            'stock_location' => $stock_location,
            'part_prefix' => $part_prefix,
        ));
    }
}
