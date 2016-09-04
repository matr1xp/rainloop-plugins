<?php

class SpamAssassinPlugin extends \RainLoop\Plugins\AbstractPlugin
{
	private $config_file = '';
	
	public function Init()
	{
		$this->addHook('filter.post-update', 'FilterSpamAssassinConfig');
		$this->addHook('filter.spam-assassin', 'FilterSpamAssassin');
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
	 * @return array
	 */
	public function configMapping()
	{	
		return array(
			\RainLoop\Plugins\Property::NewInstance('spam_assassin_wildcards')->SetLabel('Treat as wildcard domains')
				->SetType(\RainLoop\Enumerations\PluginPropertyType::BOOL)
				->SetDescription('Determines whether emails are treated as wildcard *@domain.com')
				->SetDefaultValue(true),
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
	
	public function FilterSpamAssassinConfig(&$aMap, &$oConfig) {
		$bWrite = false;
		foreach ($aMap as $oItem)
		{
			$sValue = $oConfig->Get('plugin', $oItem->Name());
			if ($this->config_file == $sValue) {
				if ($this->checkPermissions()) {
					$bWrite = true;
				}
			} elseif ($bWrite) {
				 $this->FilterSpamAssassin(str_replace(' ', ',', $sValue), $oItem->Name());
			}
		}
		
	}
	/**
	 * Action DoMessageMove() hook to write emails captured into Spam Assassin's config file
	 */
	public function FilterSpamAssassin($sEmailFrom, $sList, $bAdmin = true) {
		
		$aEmails = explode(',', $sEmailFrom);
		$this->Config()->Load();
		$sConfig = $this->Config()->Get('plugin', 'spam_assassin_config', '');
		$bWildCard = $this->Config()->Get('plugin', 'spam_assassin_wildcards', '');

		$arrEmails = array();
		foreach ($aEmails as $sEmail) {
			$re = "/(.*[^*])@(.*)/m";
			$subst = "*@$2"; 
			if ($bWildCard && preg_match($re, $sEmail)) {
				$sEmail = preg_replace($re, $subst, $sEmail);
			}
			array_push($arrEmails, $sEmail);
		}
	
		
	
		$handle = fopen($sConfig, 'r');
		$out = array();
		if ($handle) {
			$re = $config_list = '';
			 if (is_int(stripos($sList, 'blacklist'))) {
			 	$re = "/(blacklist_from\s)(.*)/m";
			 	$config_list = "blacklist_from ";
			 } elseif (is_int(stripos($sList, 'whitelist'))) {
			 	$re = "/(whitelist_from\s)(.*)/m";
			 	$config_list = "whitelist_from ";
			 }
			
		 	 while (($line = fgets($handle)) !== false) {
		 		if (preg_match($re, $line, $ematch)) {
		 			//For webmail public, we append existing 
		 			//emails from config file
		 			if (!$bAdmin) {
		 				array_push($arrEmails, $ematch[2]);
		 			}
		 		} else {
		 			if (strlen(trim($line)) > 0) {
		 				array_push($out, $line);
		 			}
		 		}
		 	 }
		 	 //We don't want duplicates
		 	 $arrEmails = array_unique($arrEmails);
		 	 fclose($handle);
		 	 
			 //We write new config file without the list emails 
			 $fp = fopen($sConfig, "w+");
			 flock($fp, LOCK_EX);
			 foreach($out as $line) {
			     fwrite($fp, $line);
			 }
			 
			 //Then we add the list emails at the bottom
			 foreach(array_filter($arrEmails) as $email) {
			 	$line = $config_list.$email."\n";
			 	fwrite($fp, $line);
			 }
			 
			 flock($fp, LOCK_UN);
			 fclose($fp);  
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
