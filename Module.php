<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\PersonalContacts;

use Aurora\Modules\ActivityHistory\Storages\Db\Storage;
use \Aurora\Modules\Contacts\Enums\StorageType;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	public static $sStorage = StorageType::Personal;
	protected static $iStorageOrder = 0;	

	public function init() 
	{
		$this->subscribeEvent('Contacts::GetStorages', array($this, 'onGetStorages'));
		$this->subscribeEvent('Contacts::IsDisplayedStorage::after', array($this, 'onAfterIsDisplayedStorage'));
		$this->subscribeEvent('Core::DeleteUser::before', array($this, 'onBeforeDeleteUser'));
		$this->subscribeEvent('Contacts::CreateContact::before', array($this, 'onBeforeCreateContact'));
		$this->subscribeEvent('Contacts::UpdateContact::before', array($this, 'onBeforeUpdateContact'));
		$this->subscribeEvent('Contacts::DeleteContact::before', array($this, 'onBeforeDeleteContact'));
		$this->subscribeEvent('Contacts::PrepareFiltersFromStorage', array($this, 'prepareFiltersFromStorage'));
		$this->subscribeEvent('Mail::ExtendMessageData', array($this, 'onExtendMessageData'));
		$this->subscribeEvent('Contacts::CheckAccessToObject::after', array($this, 'onAfterCheckAccessToObject'));
		$this->subscribeEvent('Contacts::GetContactSuggestions', array($this, 'onGetContactSuggestions'));
	}
	
	public function onGetStorages(&$aStorages)
	{
		$aStorages[self::$iStorageOrder] = self::$sStorage;
		$aStorages[self::$iStorageOrder + 1] = StorageType::Collected;
	}
	
	public function onAfterIsDisplayedStorage($aArgs, &$mResult)
	{
		if ($aArgs['Storage'] === StorageType::Collected)
		{
			$mResult = true;
		}
	}

	public function onBeforeDeleteUser(&$aArgs, &$mResult)
	{
		$oContactsDecorator = \Aurora\Modules\Contacts\Module::Decorator();
		if ($oContactsDecorator)
		{
			$oApiContactsManager = $oContactsDecorator->GetApiContactsManager();
			$aContactUUIDs = $oApiContactsManager->getContactUids(
				[
					'$AND' => [
						'IdUser' => [$aArgs['UserId'], '='],
						'Storage' => [self::$sStorage, '=']
					]
				]
			);
			if (count($aContactUUIDs) > 0)
			{
				$oContactsDecorator->DeleteContacts($aArgs['UserId'], self::$sStorage, $aContactUUIDs);
			}

			$aContactUUIDs = $oApiContactsManager->getContactUids(
				[
					'$AND' => [
						'IdUser' => [$aArgs['UserId'], '='],
						'Storage' => ['addressbook', '=']
					]
				]
			);
			if (count($aContactUUIDs) > 0)
			{
				$oContactsDecorator->DeleteContacts($aArgs['UserId'], 'addressbook', $aContactUUIDs);
			}

			$oContactsDecorator->DeleteUsersAddressBooks($aArgs['UserId']);
		}
	}
	
	public function onBeforeCreateContact(&$aArgs, &$mResult)
	{
		if (isset($aArgs['Contact']))
		{
			if (!isset($aArgs['Contact']['Storage']) || $aArgs['Contact']['Storage'] === '')
			{
				$aArgs['Contact']['Storage'] = self::$sStorage;
			}

			if ($aArgs['Contact']['Storage'] === StorageType::Collected)
			{
				$aArgs['Contact']['Storage'] = StorageType::Personal;
				$aArgs['Contact']['Auto'] = true;
			}
		}
	}

	public function onBeforeUpdateContact(&$aArgs, &$mResult)
	{
		if (isset($aArgs['Contact']))
		{
			if ($aArgs['Contact']['Storage'] === StorageType::Collected)
			{
				$aArgs['Contact']['Storage'] = StorageType::Personal;
				$aArgs['Contact']['Auto'] = true;
			}
		}
	}

	public function onBeforeDeleteContact(&$aArgs, &$mResult)
	{
		if (isset($aArgs['Storage']))
		{
			if ($aArgs['Storage'] === StorageType::Collected)
			{
				$aArgs['Storage'] = StorageType::Personal;
			}
		}
	}

	 public function prepareFiltersFromStorage(&$aArgs, &$mResult)
	{
		if (isset($aArgs['Storage']))
		{
			$iAddressBookId = 0;
			if (substr($aArgs['Storage'], 0, 11) === 'addressbook') 
			{
				$iAddressBookId = (int) substr($aArgs['Storage'], 11);
				$aArgs['Storage'] = 'addressbook';
			}
			$sStorage = $aArgs['Storage'];

			if ($sStorage === self::$sStorage || $sStorage === StorageType::All || $sStorage === StorageType::Collected || $sStorage === 'addressbook')
			{
				$iUserId = isset($aArgs['UserId']) ? $aArgs['UserId'] : \Aurora\System\Api::getAuthenticatedUserId();

				if (!isset($mResult) || !\is_array($mResult))
				{
					$mResult = array();
				}

				$bAuto = false;
				if ($sStorage === StorageType::Collected)
				{
					$sStorage = StorageType::Personal;
					$bAuto = true;
				}

				$aFilter = [
					'IdUser' => [$iUserId, '=']					
				];
				if ($sStorage === StorageType::All)
				{
					$aFilter['$OR'] = [
						'1@Storage' => [StorageType::Personal, '='],
						'2@Storage' => ['addressbook%', 'LIKE'],
						'Auto' => true
					];
				}
				else
				{
					$aFilter['Storage'] = [$sStorage, '='];
					if ($sStorage === 'addressbook' && $iAddressBookId > 0)
					{
						$aFilter['AddressBookId'] = [$iAddressBookId, '='];
					}
				}

				if (isset($aArgs['SortField']) && $aArgs['SortField'] === \Aurora\Modules\Contacts\Enums\SortField::Frequency)
				{
					$aFilter['Frequency'] = [-1, '!='];
					$aFilter['DateModified'] = ['NULL', 'IS NOT'];
				}
				else
				{
					if (!$bAuto)
					{
						// $aFilter['$OR'] = [
						// 	'1@Auto' => [false, '='],
						// 	'2@Auto' => ['NULL', 'IS']
						// ];
					}
					else
					{
						$aFilter['Auto'] = [true, '='];

					}
				}
				$mResult[]['$AND'] = $aFilter;
			}
		}
	}	
	
	public function onExtendMessageData($aData, &$oMessage)
	{
		$oApiFileCache = new \Aurora\System\Managers\Filecache();
		
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		
		foreach ($aData as $aDataItem)
		{
			$oPart = $aDataItem['Part'];
			$bVcard = $oPart instanceof \MailSo\Imap\BodyStructure && 
					($oPart->ContentType() === 'text/vcard' || $oPart->ContentType() === 'text/x-vcard');
			$sData = $aDataItem['Data'];
			if ($bVcard && !empty($sData))
			{
				$oContact = new \Aurora\Modules\Contacts\Classes\Contact('Contacts');
				try
				{
					$oContact->InitFromVCardStr($oUser->EntityId, $sData);
					
					$oContact->UUID = '';

					$bContactExists = false;
					if (0 < strlen($oContact->ViewEmail))
					{
						$aLocalContacts = \Aurora\Modules\Contacts\Module::Decorator()->GetContactsByEmails($oUser->EntityId, self::$sStorage, [$oContact->ViewEmail]);
						$oLocalContact = count($aLocalContacts) > 0 ? $aLocalContacts[0] : null;
						if ($oLocalContact)
						{
							$oContact->UUID = $oLocalContact->UUID;
							$bContactExists = true;
						}
					}

					$sTemptFile = md5($sData).'.vcf';
					if ($oApiFileCache && $oApiFileCache->put($oUser->UUID, $sTemptFile, $sData)) // Temp files with access from another module should be stored in System folder
					{
						$oVcard = \Aurora\Modules\Mail\Classes\Vcard::createInstance();

						$oVcard->Uid = $oContact->UUID;
						$oVcard->File = $sTemptFile;
						$oVcard->Exists = !!$bContactExists;
						$oVcard->Name = $oContact->FullName;
						$oVcard->Email = $oContact->ViewEmail;

						$oMessage->addExtend('VCARD', $oVcard);
					}
					else
					{
						\Aurora\System\Api::Log('Can\'t save temp file "'.$sTemptFile.'"', \Aurora\System\Enums\LogLevel::Error);
					}					
				}
				catch(\Exception $oEx)
				{
					\Aurora\System\Api::LogException($oEx);
				}
			}
		}
	}	

	public function onAfterCheckAccessToObject(&$aArgs, &$mResult)
	{
		$oUser = $aArgs['User'];
		$oContact = isset($aArgs['Contact']) ? $aArgs['Contact'] : null;

		if ($oContact instanceof \Aurora\Modules\Contacts\Classes\Contact && $oContact->Storage === self::$sStorage)
		{
			if ($oUser->Role !== \Aurora\System\Enums\UserRole::SuperAdmin && $oUser->EntityId !== $oContact->IdUser)
			{
				$mResult = false;
			}
			else
			{
				$mResult = true;
			}
		}
	}

	public function onGetContactSuggestions(&$aArgs, &$mResult)
	{
		if ($aArgs['Storage'] === 'all' || $aArgs['Storage'] === self::$sStorage)
		{
			$aContacts  = \Aurora\Modules\Contacts\Module::Decorator()->GetContacts(
				$aArgs['UserId'], 
				self::$sStorage, 
				0, 
				$aArgs['Limit'], 
				$aArgs['SortField'], 
				$aArgs['SortOrder'], 
				$aArgs['Search']
			);

			$aAbContacts  = \Aurora\Modules\Contacts\Module::Decorator()->GetContacts(
				$aArgs['UserId'], 
				'addressbook', 
				0, 
				$aArgs['Limit'], 
				$aArgs['SortField'], 
				$aArgs['SortOrder'], 
				$aArgs['Search']
			);

			$mResult['personal'] = array_merge(
				$aContacts,
				$aAbContacts
			);
		}
	}
}
