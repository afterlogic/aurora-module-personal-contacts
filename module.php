<?php

class PersonalContactsModule extends AApiModule
{
	public function init() 
	{
		$this->subscribeEvent('Contacts::GetStorage', array($this, 'onGetStorage'));
		$this->subscribeEvent('Contacts::GetContacts::before', array($this, 'onBeforeGetContacts'));
	}
	
	public function onGetStorage(&$aStorages)
	{
		$aStorages[] = 'personal';
	}
	
	public function onBeforeGetContacts(&$aArgs, &$mResult)
	{
		if (isset($aArgs['Storage']) && $aArgs['Storage'] === 'personal')
		{
			$iUserId = \CApi::getAuthenticatedUserId();
			if (!isset($aArgs['Filters']) || !is_array($aArgs['Filters']))
			{
				$aArgs['Filters'] = array();
			}
			$aArgs['Filters']['Storage'] = 'personal';
			$aArgs['Filters']['IdUser'] = $iUserId;
		}
	}
}