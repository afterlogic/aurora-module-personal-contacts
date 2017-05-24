<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\PersonalContacts;

/**
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	public function init() 
	{
		$this->subscribeEvent('Contacts::GetStorage', array($this, 'onGetStorage'));
		$this->subscribeEvent('AdminPanelWebclient::DeleteEntity::before', array($this, 'onBeforeDeleteEntity'));
		$this->subscribeEvent('Contacts::CreateContact::before', array($this, 'onBeforeCreateContact'));
		$this->subscribeEvent('Contacts::GetContacts::before', array($this, 'prepareFiltersFromStorage'));
		$this->subscribeEvent('Contacts::Export::before', array($this, 'prepareFiltersFromStorage'));
	}
	
	public function onGetStorage(&$aStorages)
	{
		$aStorages[] = 'personal';
	}
	
	public function onBeforeDeleteEntity(&$aArgs, &$mResult)
	{
		if ($aArgs['Type'] === 'User')
		{
			$oContactsDecorator = \Aurora\System\Api::GetModuleDecorator('Contacts');
			if ($oContactsDecorator)
			{
				$aFilters = [
					'$AND' => [
						'IdUser' => [$aArgs['Id'], '='],
						'Storage' => ['personal', '=']
					]
				];
				$oApiContactsManager = $oContactsDecorator->GetApiContactsManager();
				$aUserContacts = $oApiContactsManager->getContacts(\EContactSortField::Name, \Aurora\System\Enums\SortOrder::ASC, 0, 0, $aFilters, '');
				if (count($aUserContacts) > 0)
				{
					$aContactUUIDs = [];
					foreach ($aUserContacts as $oContact)
					{
						$aContactUUIDs[] = $oContact->UUID;
					}
					$oContactsDecorator->DeleteContacts($aContactUUIDs);
				}
			}
		}
	}
	
	public function onBeforeCreateContact(&$aArgs, &$mResult)
	{
		if (isset($aArgs['Contact']))
		{
			if (!isset($aArgs['Contact']['Storage']) || $aArgs['Contact']['Storage'] === '')
			{
				$aArgs['Contact']['Storage'] = 'personal';
			}
		}
	}
	
	public function prepareFiltersFromStorage(&$aArgs, &$mResult)
	{
		if (isset($aArgs['Storage']) && ($aArgs['Storage'] === 'personal' || $aArgs['Storage'] === 'all'))
		{
			$iUserId = \Aurora\System\Api::getAuthenticatedUserId();
			if (!isset($aArgs['Filters']) || !\is_array($aArgs['Filters']))
			{
				$aArgs['Filters'] = array();
			}
			
			if (isset($aArgs['SortField']) && $aArgs['SortField'] === \EContactSortField::Frequency)
			{
				$aArgs['Filters'][]['$AND'] = [
					'IdUser' => [$iUserId, '='],
					'Storage' => ['personal', '='],
					'Frequency' => [-1, '!='],
				];
			}
			else
			{
				$aArgs['Filters'][]['$AND'] = [
					'IdUser' => [$iUserId, '='],
					'Storage' => ['personal', '='],
					'Auto' => [false, '='],
				];
			}
		}
	}
}
