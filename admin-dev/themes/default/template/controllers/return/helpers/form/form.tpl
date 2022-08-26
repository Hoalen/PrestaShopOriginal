{**
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
 *}

{extends file='helpers/form/form.tpl'}

{block name="input"}
	{if $input.type == 'text_customer'}
		<span>{$customer->firstname} {$customer->lastname}</span>
		<p>
			<a class="text-muted" href="{$url_customer}">{l s='View details on the customer page' d='Admin.Orderscustomers.Help'}</a>
		</p>
	{elseif $input.type == 'text_order'}
		<span>{$text_order}</span>
		<p>
			<a class="text-muted" href="{$url_order}">{l s='View details on the order page' d='Admin.Orderscustomers.Help'}</a>
		</p>
	{elseif $input.type == 'text_address'}
		<span>{$address->alias} : {$address->firstname} {$address->lastname} - {if $address->company}{$address->company}{/if} {$address->address1} {if $address->address2}{$address->address2}{/if},{$address->postcode} {$address->city}, {if isset($address->stateName)}{$address->stateName}, {/if}{$address->country}</span>
		<p>
			<a class="text-muted" href="{$url_address}">{l s='Adresse choisie pour l\'échange' d='Admin.Orderscustomers.Help'}</a>
		</p>
	{elseif $input.type == 'pdf_order_return'}
		<p>
			{if $state_order_return == 2}
				<a class="btn" href="{$link->getPageLink('pdf-order-return', true, NULL, "id_order_return={$id_order_return|intval}&adtoken={Tools::getAdminTokenLite('AdminReturn')}&id_employee={$employee->id|intval}")|escape:'html':'UTF-8'}">
					<i class="icon-file-text"></i> {l s='Print out' d='Admin.Actions'}
				</a>
			{else}
				--
			{/if}
		</p>
	{elseif $input.name == 'state'}

{*				<pre>{$historyk|var_dump}</pre>*}
		{foreach from=$history item='state' name="loophistory"}
			<span class="label" style="background: {$state.color};color:{if Tools::getBrightness($state.color) < 128}white{else}#383838{/if}">{if $state.state == 'En attente de validation'}Défaut : {/if}{$state.state}</span> par {$state.by} le {$state.on|date_format:"%A %e %B %Y à %H:%M:%S"}<br><br>
		{/foreach}
		{$smarty.block.parent}

	{elseif $input.type == 'list_products'}

		<table class="table">
			<thead>
				<tr>
					<th>{l s='Reference' d='Admin.Global'}</th>
					<th>{l s='Product name' d='Admin.Catalog.Feature'}</th>
					<th>{l s='Explication' d='Admin.Orderscustomers.Help'}</th>
					<th>{l s='Taille souhaitée' d='Admin.Orderscustomers.Help'}</th>
					<th>{l s='SAV' d='Admin.Orderscustomers.Help'}</th>
{*					<th class="text-center">{l s='Quantity' d='Admin.Global'}</th>*}
					<th class="text-center">{l s='Photo(s)' d='Admin.Global'}</th>
					<th class="text-center">{l s='Action' d='Admin.Global'}</th>
				</tr>
			</thead>
			<tbody>
				{foreach $returnedCustomizations as $returnedCustomization}
					<tr>
						<td>{$returnedCustomization['reference']}</td>
						<td>{$returnedCustomization['name']}</td>
						<td class="text-center">{$returnedCustomization['product_quantity']|intval}</td>
						<td class="text-center">
							<a class="btn btn-default" href="{$current|escape:'html':'UTF-8'}&amp;deleteorder_return_detail&amp;id_order_detail={$returnedCustomization['id_order_detail']}&amp;id_order_return={$id_order_return}&amp;id_customization={$returnedCustomization['id_customization']}&amp;token={$token|escape:'html':'UTF-8'}">
								<i class="icon-remove"></i>
								{l s='Delete' d='Admin.Actions'}
							</a>
						</td>
					</tr>
					{assign var='productId' value=$returnedCustomization.product_id}
					{assign var='productAttributeId' value=$returnedCustomization.product_attribute_id}
					{assign var='customizationId' value=$returnedCustomization.id_customization}
					{assign var='addressDeliveryId' value=$returnedCustomization.id_address_delivery}
					{foreach $customizedDatas.$productId.$productAttributeId.$addressDeliveryId.$customizationId.datas as $type => $datas}
						<tr>
							<td colspan="4">
								<div class="form-horizontal">
									{if $type == Product::CUSTOMIZE_FILE}
										{foreach from=$datas item='data'}
											<div class="form-group">
												<span class="col-lg-3 control-label"><strong>{l s='Attachment'}</strong></span>
												<div class="col-lg-9">
													<a href="displayImage.php?img={$data['value']}&amp;name={$returnedCustomization['id_order_detail']|intval}-file{$smarty.foreach.data.iteration.iteration}" class="_blank"><img class="img-thumbnail" src="{$picture_folder}{$data['value']}_small" alt="" /></a>
												</div>
											</div>
										{/foreach}
									{elseif $type == Product::CUSTOMIZE_TEXTFIELD}
											{foreach from=$datas item='data'}
												<div class="form-group">
													<span class="control-label col-lg-3"><strong>{if $data['name']}{$data['name']}{else}{l s='Text #%d' sprintf=[$smarty.foreach.data.iteration] d='Admin.Orderscustomers.Feature'}{/if}</strong></span>
													<div class="col-lg-9">
														<p class="form-control-static">
															{$data['value']}
														</p>
													</div>
												</div>
											{/foreach}
									{/if}
								</div>
							</td>
						</tr>
					{/foreach}
				{/foreach}

				{* Classic products *}
				{foreach $products as $k => $product}

{*							<pre>{$product|var_dump}</pre>*}
{*					{for $var=1 to $product['product_quantity']}*}
					{if !isset($quantityDisplayed[$k]) || $product['product_quantity']|intval > $quantityDisplayed[$k]|intval}
						<tr>
							<td>{$product['product_reference']}</td>
							<td>{$product['product_name']}</td>
							<td>{if isset($product['question'])}{$product['question']}{/if}</td>
							<td>{if isset($product['question_size'])}{$product['question_size']}{/if}</td>
							<td>
								{if isset($product['question']) && $product['question']|strstr:"ECHANGE"}
									<select class="motif" data-pid="{$k}" onchange="onchangeSelect(this)" onfocus="onfocusselect(this)">
									<option value="0">-</option>
									<optgroup value="RMBT" label="Remboursement">
										<option {if 496 == $product['sav_id']}selected{/if} value="496">AUTRES RAISONS</option>
{*										<option value="494">{l s='Defect' d='Shop.Theme.Customeraccount'}</option>*}
{*										<option value="492">{l s='Too Little' d='Shop.Theme.Customeraccount'}</option>*}
{*										<option value="493">{l s='Too Big' d='Shop.Theme.Customeraccount'}</option>*}
{*										<option value="495">{l s='NOT COMPLYING WITH EXPECTATIONS' d='Shop.Theme.Customeraccount'}</option>*}
										<option {if 498 == $product['sav_id']}selected{/if} value="498">INDISPONIBILITE DU PRODUIT</option>
{*										<option value="624">{l s='I don\'t like the cup' d='Shop.Theme.Customeraccount'}</option>*}
										<option {if 487 == $product['sav_id']}selected{/if} value="497">GESTE COMMERCIAL</option>
{*										<option value="626">{l s='NOT CONFORM TO PHOTO' d='Shop.Theme.Customeraccount'}</option>*}
{*										<option value="625">{l s='I don\'t like the color / material' d='Shop.Theme.Customeraccount'}</option>*}
									</optgroup>
{*									<optgroup value="ECHANGE" label="Échange">*}
{*										<option value="491">AUTRES RAISONS</option>*}
{*										<option value="413">ECHANGE</option>*}
{*										<!--<option value="490">NON CONFORME AUX ATTENTES</option>-->*}
{*										<!--<option value="620">LA COUPE NE ME PLAIT PAS</option>-->*}
{*										<option value="489">{l s='Defect' d='Shop.Theme.Customeraccount'}</option>*}
{*										<!--<option value="623">NON CONFORME A LA PHOTO</option>-->*}
{*										<option value="487">{l s='Too Little' d='Shop.Theme.Customeraccount'}</option>*}
{*										<option value="488">{l s='Too Big' d='Shop.Theme.Customeraccount'}</option>*}
{*										<!--<option value="622">LA COULEUR / MATIERE NE ME PLAIT PAS</option>-->*}
{*									</optgroup>*}
								</select><br>{/if}
								<select onchange="onchangeSelectValid(this)" data-pid="{$k}" >
									<option {if !$product['valid']}selected{/if} value="0">Defectueux</option>
									<option {if $product['valid']}selected{/if} value="1">Conforme</option>
								</select>
							</td>
{*							<td class="text-center">{$product['product_quantity']}</td>*}
							<td class="text-center">
								{if isset($product['files'])}{foreach from=$product['files'] key=$key item=$photo name="boucleSize"}<a href="../../../upload/{$product['files'][$key]}" target="_blank">{$key+1}</a>{if not $smarty.foreach.boucleSize.last}&nbsp;-&nbsp;{/if}{/foreach}{/if}
							</td>
							<td class="text-center">
								{if $state_order_return < 4}
									<a class="btn btn-default"  href="{$current|escape:'html':'UTF-8'}&amp;deleteorder_return_detail&amp;id_order_detail={$k}&amp;id_order_return={$id_order_return}&amp;token={$token|escape:'html':'UTF-8'}">
										<i class="icon-remove"></i>
										{l s='Delete' d='Admin.Actions'}
									</a>
								{/if}
							</td>
						</tr>
					{/if}
{*					{/for}*}
				{/foreach}
{*				en cas de retours dans un colis groupé et pour éviter toute confusion, la plupart des infos sont nécessaires que sur un seul retour*}
{*				finalement les mathildes ne préfèrent plus cette méthode on commente au cas où*}
{*				{if $ids == 'Non' || $ids|@end == $id_order_return}*}
				{if $state_order_return < 2}
				<tr>
					<td>PORT</td>
					<td>Frais de port à la charge du client ?</td>
					<td>Client Salted de Niveau : {$customer->level} {if $customer->fdp}.{else}!{/if}</td>
					<td></td>
					<td><select {if false} disabled{else}onchange="onchangeSelectFDP(this)" {/if}><option value="1" {if $fdp == 'Oui'}selected{/if}>Oui  5€</option><option value="0" {if $fdp == 'Non'}selected {/if}>Non 0 €</option></select></td>
{*					<td></td>*}
					<td></td>
					<td></td>
				</tr>
				{/if}
{*				{/if}*}

				{if !empty($forgotten_products)}
					<tr onclick="javascript:jQuery('.forgotten').toggleClass('hidden');"><td colspan="8"><u>Cliquez ici pour <span class="forgotten">afficher</span><span class="forgotten hidden">cacher</span> les lignes de produits du colis qui auraient pu être oublié par le client</u></td></tr>
				{/if}
				{foreach $forgotten_products as $fk => $forgotten_product}

					{for $var=1 to $forgotten_product.product_quantity-$forgotten_product.product_quantity_return}
						<tr class="hidden forgotten">
							<td>{$forgotten_product['product_reference']}</td>
							<td>{$forgotten_product['product_name']}</td>
							<td>Produit retourné absent du formulaire</td>
							<td></td>
							<td>

							</td>
{*							<td class="text-center">{$forgotten_product['product_quantity']}</td>*}
							<td class="text-center"></td>
							<td class="text-center">
								<a class="btn btn-default"  href="{$current|escape:'html':'UTF-8'}&amp;addorder_return_detail&amp;id_order_detail={$forgotten_product['id_order_detail']}&amp;id_order_return={$id_order_return}&amp;token={$token|escape:'html':'UTF-8'}">
									<i class="icon-add"></i>
									{l s='Add' d='Admin.Actions'}
								</a>
							</td>
						</tr>
					{/for}
				{/foreach}
			</tbody>
		</table>
	<textarea style="display: none;" id="motifjson" cols="67" rows="3" name="returnTextJSON" class=" form-control" >{$returnTextJSON}</textarea>
			{*{if $customer->fileUpload}
				<img width="100" src="../../../upload/{$customer->fileUpload}" />
				<a href="../../../upload/{$customer->fileUpload}" target="_blank">Open file</a>
			{/if}*}
	{else}
		{$smarty.block.parent}
{*		<pre>{$input|var_dump}</pre>*}
	{/if}

{literal}
	<script>

		var onchangeSelectValid = function(that) {

			var pid = parseFloat(jQuery(that).attr('data-pid'));
			console.log(pid);
			var value = jQuery(that).val();
			var motif = jQuery('#motifjson').text()?JSON.parse(jQuery('#motifjson').text()):new Object();
			if (undefined === motif[pid]) motif[pid] = {};
			if (undefined === motif[pid].sav) motif[pid].sav = {};
			motif[pid].sav.valid = value;

			jQuery('#motifjson').text(JSON.stringify(motif));
		}
		var onchangeSelectFDP = function(that) {

			var value = jQuery(that).val();
			var motif = jQuery('#motifjson').text()?JSON.parse(jQuery('#motifjson').text()):new Object();
			motif.fdp = value;

			jQuery('#motifjson').text(JSON.stringify(motif));
		}

		var onchangeSelect = function(that) {
			var pid = parseFloat(jQuery(that).attr('data-pid'));
			console.log(pid);
			var opt = jQuery(that).find(':selected');
			var sel = opt.text();
			var selv = opt.val();
			var og = opt.closest('optgroup').attr('label');
			var ogv = opt.closest('optgroup').attr('value');
			//alert(sel);
			//alert(og);

			if (selv > 0) {
				jQuery(that).blur().find(':selected').text(og + ' - ' + sel);
				var motif = jQuery('#motifjson').text()?JSON.parse(jQuery('#motifjson').text()):new Object();
				if (undefined === motif[pid]) motif[pid] = {};
				if (undefined === motif[pid].sav) motif[pid].sav = {};
				motif[pid].sav.id=selv;
				motif[pid].sav.question=ogv+' - ' + sel;

				jQuery('#motifjson').text(JSON.stringify(motif));



			}
			else {
				var motif = jQuery('#motifjson').text()?JSON.parse(jQuery('#motifjson').text()):new Object();
				delete motif[pid].sav;
				jQuery('#motifjson').text(JSON.stringify(motif));

			}
		};
		var onfocusselect = function(that) {
			jQuery(that).find('option').each(function(){
				console.log(jQuery(this).text());
				var t=jQuery(this).text().split(' - ');
				jQuery(this).text(t[1]);

			});

		};
	</script>
{/literal}
{/block}
