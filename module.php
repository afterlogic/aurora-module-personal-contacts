<?php

class PersonalContactsModule extends AApiModule
{
	public function init() 
	{
		$this->subscribeEvent('Contacts::GetStorage', array($this, 'onGetStorage'));
		$this->subscribeEvent('AdminPanelWebclient::DeleteEntity::before', array($this, 'onBeforeDeleteEntity'));
		$this->subscribeEvent('Contacts::GetContacts::before', array($this, 'onBeforeGetContacts'));
	}
	
	public function onGetStorage(&$aStorages)
	{
		$aStorages[] = 'personal';
	}
	
	public function onBeforeDeleteEntity(&$aArgs, &$mResult)
	{
		if ($aArgs['Type'] === 'User')
		{
			$oContactsDecorator = \CApi::GetModuleDecorator('Contacts');
			if ($oContactsDecorator)
			{
				$aFilters = [
					'$AND' => [
						'IdUser' => [$aArgs['Id'], '='],
						'Storage' => ['personal', '=']
					]
				];
				$oApiContactsManager = $oContactsDecorator->GetApiContactsManager();
				$aUserContacts = $oApiContactsManager->getContactItems(EContactSortField::Name, ESortOrder::ASC, 0, 0, $aFilters, 0);
				if (count($aUserContacts) > 0)
				{
					$aContactIds = [];
					foreach ($aUserContacts as $oContact)
					{
						$aContactIds[] = $oContact->iId;
					}
					$oContactsDecorator->DeleteContacts($aContactIds);
				}
			}
		}
	}
	
	public function onBeforeGetContacts(&$aArgs, &$mResult)
	{
		if (isset($aArgs['Storage']) && ($aArgs['Storage'] === 'personal' || $aArgs['Storage'] === 'all'))
		{
			$iUserId = \CApi::getAuthenticatedUserId();
			if (!isset($aArgs['Filters']) || !is_array($aArgs['Filters']))
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