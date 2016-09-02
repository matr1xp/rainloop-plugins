<?php

class SpamAssassinPlugin extends \RainLoop\Plugins\AbstractPlugin
{
	private $config_file = '';
	
	public function Init()
	{
		$this->addHook('filter.login-credentials', 'FilterLoginCredentials');
		$this->addHook('filter.post-config', 'FilterPostAction');
		$this->addJs('js/app.js', true);
		
		$config_file = \trim($this->Config()->Get('plugin', 'spam_assassin_config', ''));
		if ($config_file) { 
			if (!is_file($config_file)) {
				error_log("*** DEBUG: $config_file NOT FOUND! ***");
			} else {
				$this->config_file = $config_file;
				$this->checkPermissions();
				$arrList = $this->readConfigFile();
				//We write parsed list from spam assassin's cf file to plugin's config file
				$this->writePluginConfig($arrList);
			}
		}
	}

	/**
	 * @param string $sEmail
	 * @param string $sLogin
	 * @param string $sPassword
	 *
	 * @throws \RainLoop\Exceptions\ClientException
	 */
	public function FilterLoginCredentials(&$sEmail, &$sLogin, &$sPassword)
	{
		$sBlackList = \trim($this->Config()->Get('plugin', 'spam_assassin_blacklist', ''));
		if (0 < \strlen($sBlackList) && \RainLoop\Plugins\Helper::ValidateWildcardValues($sEmail, $sBlackList))
		{
			$sWhiteList = \trim($this->Config()->Get('plugin', 'spam_assassin_whitelist', ''));
			if (0 === \strlen($sExceptions) || !\RainLoop\Plugins\Helper::ValidateWildcardValues($sEmail, $sWhiteList))
			{
				throw new \RainLoop\Exceptions\ClientException(
					 \RainLoop\Notifications::AccountNotAllowed);
			}
		}
	}

	/**
	 * @return array
	 */
	public function configMapping()
	{	
		return array(
			\RainLoop\Plugins\Property::NewInstance('spam_assassin_config')
			    ->SetLabel('SpamAssassin Config filepath')
				->SetAllowedInJs(true)
				->SetDescription("SpamAssassin config filepath. Make sure it's writeable by `".exec('whoami')."`")
				->SetDefaultValue('/etc/spamassassin/local.cf'),
			\RainLoop\Plugins\Property::NewInstance('spam_assassin_blacklist')
				->SetLabel('Black List')
				->SetAllowedInJs(true)
				->SetType(\RainLoop\Enumerations\PluginPropertyType::STRING_TEXT)
				->SetDescription('Emails black list, space as delimiter, wildcard supported.')
				->SetDefaultValue('*@domain1.com user@domain2.com'),
			\RainLoop\Plugins\Property::NewInstance('spam_assassin_whitelist')
				->SetLabel('White List')
				->SetAllowedInJs(true)
				->SetType(\RainLoop\Enumerations\PluginPropertyType::STRING_TEXT)
				->SetDescription('Emails white list, space as delimiter, wildcard supported.')
				->SetDefaultValue('demo@domain1.com *@domain2.com admin@*')
		);
	}
	/**
	 * Reads spam assassin configuration file 
	 * and return array of blacklist and whitelist values
	 * @return array
	 */
	public function readConfigFile() {
		$arrBlackList = array();
		$arrWhiteList = array();
		if ($this->config_file) {
			$handle = fopen($this->config_file, 'r');
			if ($handle) {
				 while (($line = fgets($handle)) !== false) {
						if (is_int(stripos($line, 'blacklist_from'))) {
							$arrBl = explode(' ', $line);
							if ($arrBl[1]) {
								array_push($arrBlackList, \trim($arrBl[1]));
							}
						}
				 		if (is_int(stripos($line, 'whitelist_from'))) {
							$arrBl = explode(' ', $line);
							if ($arrBl[1]) {
								array_push($arrWhiteList, \trim($arrBl[1]));
							}
						}
				 }
			
			    fclose($handle);
			}
		}
		return array('spam_assassin_blacklist' => $arrBlackList, 
					 'spam_assassin_whitelist' => $arrWhiteList
					 );
	}
	
	public function writePluginConfig($arrList) {
		$this->Config()->Load();
		$this->Config()->Set('plugin', 'spam_assassin_blacklist', implode(' ', $arrList['spam_assassin_blacklist']));
		$this->Config()->Set('plugin', 'spam_assassin_whitelist', implode(' ', $arrList['spam_assassin_whitelist']));
		$this->Config()->Save();
	}
	
	public function FilterPostAction(&$aMap, &$oConfig) {
		error_log("*** DEBUG: FilterPostAction(".$this->config_file.")");
		$bWrite = false;
		foreach ($aMap as $oItem)
		{
			$sValue = $oConfig->Get('plugin', $oItem->Name());
			//error_log($oItem->Name()." => ". $oConfig->Get('plugin', $oItem->Name()));
			if ($this->config_file == $sValue) {
				if ($this->checkPermissions()) {
					$bWrite = true;
				}
			} elseif ($bWrite) {
				error_log(" *** Writing ".$sValue);
			}
		}
		
	}
	
	private function checkPermissions() {
		if ($this->config_file) {
			$user = exec('whoami');
			$owner = posix_getpwuid(fileowner($this->config_file))['name'];
			$group = posix_getgrgid(filegroup($this->config_file))['name'];
			if (!in_array($user, array($owner, $group))) {
				error_log("*** ERROR: $this->config_file is not writeable by PHP! ***");
			}
			return true;
		}
		return false;
	}
}
