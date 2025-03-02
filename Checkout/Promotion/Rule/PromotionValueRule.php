<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Rule\Container\FilterRule;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal (flag:FEATURE_NEXT_18982)
 */
class PromotionValueRule extends FilterRule
{
    protected float $amount;

    protected string $operator;

    public function __construct(string $operator = self::OPERATOR_EQ, ?float $amount = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->amount = (float) $amount;
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $promotions = new LineItemCollection($scope->getCart()->getLineItems()->filterFlatByType(LineItem::PROMOTION_LINE_ITEM_TYPE));
        $filter = $this->filter;
        if ($filter !== null) {
            $context = $scope->getSalesChannelContext();

            $promotions = $promotions->filter(static function (LineItem $lineItem) use ($filter, $context) {
                $scope = new LineItemScope($lineItem, $context);

                return $filter->match($scope);
            });
        }

        $promotionAmount = $promotions->getPrices()->sum()->getTotalPrice() * -1;

        switch ($this->operator) {
            case self::OPERATOR_GTE:
                return FloatComparator::greaterThanOrEquals($promotionAmount, $this->amount);

            case self::OPERATOR_LTE:
                return FloatComparator::lessThanOrEquals($promotionAmount, $this->amount);

            case self::OPERATOR_GT:
                return FloatComparator::greaterThan($promotionAmount, $this->amount);

            case self::OPERATOR_LT:
                return FloatComparator::lessThan($promotionAmount, $this->amount);

            case self::OPERATOR_EQ:
                return FloatComparator::equals($promotionAmount, $this->amount);

            case self::OPERATOR_NEQ:
                return FloatComparator::notEquals($promotionAmount, $this->amount);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        return [
            'amount' => [new NotBlank(), new Type('numeric')],
            'operator' => [
                new NotBlank(),
                new Choice(
                    [
                        self::OPERATOR_NEQ,
                        self::OPERATOR_GTE,
                        self::OPERATOR_LTE,
                        self::OPERATOR_EQ,
                        self::OPERATOR_GT,
                        self::OPERATOR_LT,
                    ]
                ),
            ],
        ];
    }

    public function getName(): string
    {
        return 'promotionValue';
    }
}
