<?php

include_once("./Services/UIComponent/classes/class.ilUserInterfaceHookPlugin.php");
 
/**
 * Basic plugin file
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 */
class ilExtendedTestStatisticsPlugin extends ilUserInterfaceHookPlugin
{
	public function getPluginName()
	{
		return "ExtendedTestStatistics";
	}

	/**
	 * Get debugging output of different value formats
	 * @return bool
	 */
	public function debugFormats()
	{
		return false;
	}

	/**
	 * Update all or selected languages
	 * (Overridden from ilPlugin to get also the lang files of extensions)
	 *
	 * @var array|null	$a_lang_keys	keys of languages to be updated (null for all)
	 */
	public function updateLanguages($a_lang_keys = null)
	{
		ilGlobalCache::flushAll();
		include_once("./Services/Language/classes/class.ilObjLanguage.php");

		if (!isset($a_lang_keys))
		{
			$a_lang_keys = array();

			// from 5.2 on ilObjLanguage::_getInstalledLanguages() can be used
			$languages = ilObject::_getObjectsByType("lng");
			foreach ($languages as $lang)
			{
				$langObj = new ilObjLanguage($lang["obj_id"], false);
				if ($langObj->isInstalled())
				{
					$a_lang_keys[] = $langObj->getKey();
				}
				unset($langObj);
			}
		}

		$langs = array_merge(
			$this->getAvailableLangFiles($this->getLanguageDirectory()),
			$this->getHookedLangFiles());

		$prefix = $this->getPrefix();

		foreach($langs as $lang)
		{
			// check if the language should be updated, otherwise skip it
			if (!in_array($lang['key'], $a_lang_keys) )
			{
				continue;
			}

			$lang_array = array();
			$local_changes = array();

			// get locally changed variables of the module (these should be kept)
			if (method_exists('ilObjLanguage', '_getLocalChangesByModule'))
			{
				$local_changes = ilObjLanguage::_getLocalChangesByModule($lang['key'], $prefix);
			}

			// get language data
			$txt = file($lang["path"]);
			if (is_array($txt))
			{
				foreach ($txt as $row)
				{
					if ($row[0] != "#" && strpos($row, "#:#") > 0)
					{
						$a = explode("#:#",trim($row));
						$identifier = $prefix."_".trim($a[0]);
						$value = trim($a[1]);

						if (isset($local_changes[$identifier]))
						{
							$lang_array[$identifier] = $local_changes[$identifier];
						}
						else
						{
							$lang_array[$identifier] = $value;
							ilObjLanguage::replaceLangEntry($prefix, $identifier, $lang["key"], $value);
						}
						//echo "<br>-$prefix-".$prefix."_".trim($a[0])."-".$lang["key"]."-";
					}
				}
			}

			ilObjLanguage::replaceLangModule($lang["key"], $prefix, $lang_array);
		}
	}

	/**
	 * Get the lang files of hooked evaluations (slot simulation)
	 * @return array
	 */
	protected function getHookedLangFiles()
	{
		$langs = array();
		$lang_files = glob('./Customizing/global/plugins/Modules/Test/Evaluations/*/lang/ilias_*.lang');
		if (!empty($lang_files))
		{
			foreach ($lang_files as $file)
			{
				$langs[] = array(
					"key" => substr(basename($file), 6, 2),
					"file" => basename($file),
					"path" => $file
				);
			}
		}
		return $langs;
	}
}

?>