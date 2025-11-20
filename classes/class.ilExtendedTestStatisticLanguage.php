<?php

class ilExtendedTestStatisticLanguage extends ilPluginLanguage
{
    /**
     * Update all or selected languages
     *
     * @var array|null $a_lang_keys keys of languages to be updated (null for all)
     */
    public function updateLanguages(?array $a_lang_keys = null): void
    {
        // get the keys of all installed languages if keys are not provided
        if (!isset($a_lang_keys)) {
            $a_lang_keys = [];
            foreach (ilObjLanguage::getInstalledLanguages() as $langObj) {
                if ($langObj->isInstalled()) {
                    $a_lang_keys[] = $langObj->getKey();
                }
            }
        }

        // ExtendedTestStatistics: add hooked lang files
        $langs = array_merge($this->getAvailableLangFiles(), $this->getHookedLangFiles());
        // ExtendedTestStatistics.

        $prefix = $this->getPrefix();

        foreach ($langs as $lang) {
            // check if the language should be updated, otherwise skip it
            if (!in_array($lang['key'], $a_lang_keys)) {
                continue;
            }

            // ExtendedTestStatistics: path is only provided by hooked files
            if (isset($lang['path'])) {
                $txt = file($lang["path"]);
            } else {
                $txt = file($this->getLanguageDirectory() . "/" . $lang["file"]);
            }
            // ExtendedTestStatistics.

            $lang_array = [];

            // get locally changed variables of the module (these should be kept)
            $local_changes = ilObjLanguage::_getLocalChangesByModule($lang['key'], $prefix);

            // get language data
            if (is_array($txt)) {
                foreach ($txt as $row) {
                    if ($row[0] != "#" && strpos($row, "#:#") > 0) {
                        $a = explode("#:#", trim($row));
                        $identifier = $prefix . "_" . trim($a[0]);
                        $value = trim($a[1]);

                        if (isset($local_changes[$identifier])) {
                            $lang_array[$identifier] = $local_changes[$identifier];
                        } else {
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
     */
    protected function getHookedLangFiles(): array
    {
        $langs = [];
        $lang_files = glob(__DIR__ . '/../addons/*/lang/ilias_*.lang');
        if (!empty($lang_files)) {
            foreach ($lang_files as $file) {
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
