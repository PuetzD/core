<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Facade\Traits\ContainerFactoryTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\DiscountTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsCountTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsGetTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsHasTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsRemoveTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\SurchargeTrait;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

class CartFacade
{
    use DiscountTrait;
    use SurchargeTrait;
    use ItemsGetTrait;
    use ItemsRemoveTrait;
    use ItemsHasTrait;
    use ItemsCountTrait;
    use ContainerFactoryTrait;

    private Cart $cart;

    /**
     * @internal
     */
    public function __construct(Services $services, Cart $cart)
    {
        $this->services = $services;
        $this->cart = $cart;
    }

    public function items(): ItemFunctions
    {
        return new ItemFunctions($this->cart->getLineItems(), $this->services);
    }

    public function products(): ProductFunctions
    {
        return new ProductFunctions($this->cart->getLineItems(), $this->services);
    }

    public function calculate(): void
    {
        $this->cart = $this->cart->getBehavior()->disableHooks(function () {
            return $this->services->calculate($this->cart, $this->cart->getBehavior());
        });
    }

    public function price(): CartPriceFacade
    {
        return new CartPriceFacade($this->cart->getPrice(), $this->services);
    }

    public function errors(): ErrorFunctions
    {
        return new ErrorFunctions($this->cart->getErrors());
    }

    protected function getItems(): LineItemCollection
    {
        return $this->cart->getLineItems();
    }
}
