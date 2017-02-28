<?php

namespace Aurora\Modules;

class PersonalContactsModule extends \Aurora\System\AbstractModule
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
				$aUserContacts = $oApiContactsManager->getContacts(\EContactSortField::Name, \ESortOrder::ASC, 0, 0, $aFilters, '');
				if (count($aUserContacts) > 0)
				{
					$aContactIds = [];
					foreach ($aUserContacts as $oContact)
					{
						$aContactIds[] = $oContact->EntityId;
					}
					$oContactsDecorator->DeleteContacts($aContactIds);
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
			$aArgs['Filters'][]['$AND'] = [
				'IdUser' => [$iUserId, '='],
				'Storage' => ['personal', '='],
			];
		}
	}
}
