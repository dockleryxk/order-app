<?php
namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * CartProduct
 *
 * @ORM\Table(name="cart_products")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\CartProductRepository")
 */
class CartProduct
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Cart", inversedBy="cart_products")
     * @ORM\JoinColumn(name="cart_id", referencedColumnName="id")
     */
    private $cart;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Part")
     * @ORM\JoinColumn(name="part_id", referencedColumnName="id")
     */
    private $part;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\StockLocation")
     * @ORM\JoinColumn(name="stock_location_id", referencedColumnName="id")
     */
    private $stockLocation;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\PartNumberPrefix")
     * @ORM\JoinColumn(name="part_number_prefix_id", referencedColumnName="id")
     */
    private $partNumberPrefix;


    /**
     * @var int
     *
     * @ORM\Column(name="quantity", type="integer")
     */
    private $quantity = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="ship_quantity", type="integer")
     */
    private $shipQuantity = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="back_order_quantity", type="integer")
     */
    private $backOrderQuantity = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="note", type="text", nullable=true)
     */
    private $note;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\CartProductLineNumber", mappedBy="cartProduct")
     */
    private $cartProductLineNumbers;

    public function __construct()
    {
        $this->cartProductLineNumbers = new ArrayCollection();
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getCart()
    {
        return $this->cart;
    }

    /**
     * @param mixed $cart
     */
    public function setCart($cart)
    {
        $this->cart = $cart;
    }

    /**
     * @return mixed
     */
    public function getPart()
    {
        return $this->part;
    }

    /**
     * @param mixed $part
     */
    public function setPart($part)
    {
        $this->part = $part;
    }

    /**
     * @return string
     */
    public function getStockLocation()
    {
        return $this->stockLocation;
    }

    /**
     * @param string $stockLocation
     */
    public function setStockLocation($stockLocation)
    {
        $this->stockLocation = $stockLocation;
    }

    /**
     * @return int
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * @param int $quantity
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;
    }

    /**
     * @return int
     */
    public function getShipQuantity()
    {
        return $this->shipQuantity;
    }

    /**
     * @param int $shipQuantity
     */
    public function setShipQuantity($shipQuantity)
    {
        $this->shipQuantity = $shipQuantity;
    }

    /**
     * @return int
     */
    public function getBackOrderQuantity()
    {
        return $this->backOrderQuantity;
    }

    /**
     * @param int $backOrderQuantity
     */
    public function setBackOrderQuantity($backOrderQuantity)
    {
        $this->backOrderQuantity = $backOrderQuantity;
    }

    /**
     * @return string
     */
    public function getNote()
    {
        return $this->note;
    }

    /**
     * @param string $notes
     */
    public function setNote($note)
    {
        $this->note = $note;
    }

    public function addCartProductLineNumber(\AppBundle\Entity\CartProductLineNumber $cartProductLineNumber)
    {
        $this->cartProductLineNumbers[] = $cartProductLineNumber;

        return $this;
    }

    /**
     * Remove payTypes
     *
     * @param \AppBundle\Entity\User $user
     */
    public function removeCartProductLineNumber(\AppBundle\Entity\CartProductLineNumber $cartProductLineNumber)
    {
        $this->cartProductLineNumbers->removeElement($cartProductLineNumber);
    }

    /**
     * Get payTypes
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCartProductLineNumbers()
    {
        return $this->cartProductLineNumbers;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return mixed
     */
    public function getPartNumberPrefix()
    {
        return $this->partNumberPrefix;
    }

    /**
     * @param mixed $partNumberPrefix
     */
    public function setPartNumberPrefix($partNumberPrefix)
    {
        $this->partNumberPrefix = $partNumberPrefix;
    }


}
