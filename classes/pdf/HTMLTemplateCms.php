<?php
/**
 * 2007-2018 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
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
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2018 PrestaShop SA
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
 
/**
 * @since 1.5
 */
class HTMLTemplateCmsCore extends HTMLTemplate
{
    public $cms;

    /**
     * @param CMS $cms
     * @param $smarty
     * @throws PrestaShopException
     */
    public function __construct(CMS $cms, $smarty)
    {
        $this->smarty = $smarty;
        $this->cms = new CMS($cms->id_cms);

    }

    /**
     * Returns the template's HTML content
     *
     * @return string HTML content
     */
    public function getContent()
    {
        $content = CMS::getCMSContent($this->cms->id_cms,Context::getContext()->language->id,Context::getContext()->shop->id);
        $this->smarty->assign(array(
            'content' => $this->cms->createContentForCGV($content['content'])
        ));

        $tpls = array(
            'cms' => $this->smarty->fetch($this->getTemplate('cms')),
        );
        $this->smarty->assign($tpls);

        return $this->smarty->fetch($this->getTemplate('cms'));
    }

    /**
     * Returns the template filename
     *
     * @return string filename
     */
    public function getFilename()
    {
        //return 'Hoalen-return-'Configuration::get('PS_RETURN_PREFIX', Context::getContext()->language->id, null, $this->order->id_shop).sprintf('%06d', $this->order_return->id).'.pdf';
        return 'Hoalen-cgv-'.date('Y').'-'.$this->cms->id_cms.'.pdf';
    }

    /**
     * Returns the template filename when using bulk rendering
     *
     * @return string filename
     */
    public function getBulkFilename()
    {
        return 'cmss.pdf';
    }

    /**
     * Returns the template's HTML header
     *
     * @return string HTML header
     */
    public function getHeader()
    {
        $this->assignCommonHeaderData();
        $this->smarty->assign(array('header' => Context::getContext()->getTranslator()->trans('CMS', array(), 'Shop.Pdf')));

        return $this->smarty->fetch($this->getTemplate('header'));
    }


    /**
     * Assign common header data to smarty variables
     */

    public function assignCommonHeaderData()
    {
        $this->setShopId();
        $id_shop = (int)$this->shop->id;
        $shop_name = Configuration::get('PS_SHOP_NAME', null, null, $id_shop);

        $path_logo = 'themes/hoalen4/pdf/brfr2x.png';

        $width = 0;
        $height = 0;
        if (!empty($path_logo)) {
            list($width, $height) = getimagesize($path_logo);
        }

        // Limit the height of the logo for the PDF render
        $maximum_height = 100;
        if ($height > $maximum_height) {
            $ratio = $maximum_height / $height;
            $height *= $ratio;
            $width *= $ratio;
        }

        $this->smarty->assign(array(
            'logo_path' => $path_logo,
            'img_ps_dir' => 'http://'.Tools::getMediaServer(_PS_IMG_)._PS_IMG_,
            'img_update_time' => Configuration::get('PS_IMG_UPDATE_TIME'),
            'date' => '',
            'title' => '',
            'titre' => '',
            'shop_name' => $shop_name,
            'shop_details' => Configuration::get('PS_SHOP_DETAILS', null, null, (int)$id_shop),
            'width_logo' => $width,
            'height_logo' => $height
        ));
    }
}
