<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Product\Grid\Data\Factory;

use Currency;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Adapter\Product\ProductDataProvider;
use PrestaShop\PrestaShop\Adapter\Product\Repository\ProductRepository;
use PrestaShop\PrestaShop\Adapter\Tax\TaxComputer;
use PrestaShop\PrestaShop\Core\Domain\Country\ValueObject\CountryId;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\ValueObject\TaxRulesGroupId;
use PrestaShop\PrestaShop\Core\Grid\Data\Factory\GridDataFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;
use PrestaShop\PrestaShop\Core\Image\ImageProviderInterface;
use PrestaShop\PrestaShop\Core\Localization\Locale;
use PrestaShop\PrestaShop\Core\Localization\Locale\Repository;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductId;

/**
 * Decorates original grid data and returns modified prices for grid display as well as calculated price with taxes.
 */
final class ProductGridDataFactoryDecorator implements GridDataFactoryInterface
{
    /**
     * @var GridDataFactoryInterface
     */
    private $productGridDataFactory;

    /**
     * @var Locale
     */
    private $locale;

    /**
     * @var int
     */
    private $defaultCurrencyId;

    /**
     * @var ProductDataProvider
     */
    private $productDataProvider;

    /**
     * @var ImageProviderInterface
     */
    private $productImageProvider;

    /**
     * @var TaxComputer
     */
    private $taxComputer;
    /**
     * @var \PrestaShop\PrestaShop\Adapter\Product\Repository\ProductRepository
     */
    private $productRepository;
    /**
     * @var int
     */
    private $countryId;

    /**
     * @param GridDataFactoryInterface $productGridDataFactory
     * @param Repository $localeRepository
     * @param string $contextLocale
     * @param int $defaultCurrencyId
     * @param ProductDataProvider $productDataProvider
     * @param ImageProviderInterface $productImageProvider
     */
    public function __construct(
        GridDataFactoryInterface $productGridDataFactory,
        Repository $localeRepository,
        string $contextLocale,
        int $defaultCurrencyId,
        ProductDataProvider $productDataProvider,
        ImageProviderInterface $productImageProvider,
        TaxComputer $taxComputer,
        ProductRepository $productRepository,
        int $countryId
    ) {
        $this->productGridDataFactory = $productGridDataFactory;

        $this->locale = $localeRepository->getLocale(
            $contextLocale
        );

        $this->defaultCurrencyId = $defaultCurrencyId;
        $this->productDataProvider = $productDataProvider;
        $this->productImageProvider = $productImageProvider;
        $this->taxComputer = $taxComputer;
        $this->productRepository = $productRepository;
        $this->countryId = $countryId;
    }

    /**
     * {@inheritdoc}
     */
    public function getData(SearchCriteriaInterface $searchCriteria): GridData
    {
        $productData = $this->productGridDataFactory->getData($searchCriteria);

        $modifiedRecords = $this->applyModification(
            $productData->getRecords()->all()
        );

        return new GridData(
            new RecordCollection($modifiedRecords),
            $productData->getRecordsTotal(),
            $productData->getQuery()
        );
    }

    /**
     * Applies modifications for product grid.
     *
     * @param array $products
     *
     * @return array
     */
    private function applyModification(array $products): array
    {
        $currency = new Currency($this->defaultCurrencyId);
        foreach ($products as $i => $product) {
            $productObject = $this->productRepository->get(new ProductId((int) $product['id_product']));

            $products[$i]['price_tax_excluded'] = $this->locale->formatPrice(
                $products[$i]['price_tax_excluded'],
                $currency->iso_code
            );

            $products[$i]['price_tax_included'] = $this->locale->formatPrice(
                $this->taxComputer->computePriceWithTaxes(
                    new DecimalNumber($product['price_tax_excluded']),
                    new TaxRulesGroupId((int) $productObject->getIdTaxRulesGroup()),
                    new CountryId($this->countryId)
                ),
                $currency->iso_code
            );

            $products[$i]['image'] = $this->productImageProvider->getPath($product['id_image']);
        }

        return $products;
    }
}
