<?php
// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

class PlgSystemNotifynewcontentInstallerScript extends InstallerScript
{
    public function preflight($type, $parent)
    {
    }

    public function postflight($type, $parent)
    {
        if ($type == 'install') {
            $this->addProfileField();
        } elseif ($type == 'update') {
            // Actions to perform after update
        } elseif ($type == 'uninstall') {
            $this->removeProfileField();
        }
    }

    public function uninstall($parent)
    {
        $this->removeProfileField();
    }

    protected function addProfileField()
    {
        Log::add('Adding profile field "notify-on-new-content"...', Log::DEBUG, 'install');
        $db = Factory::getDbo();
        $fieldTitle = Text::_('PLG_SYSTEM_NOTIFYNEWCONTENT_NOTIFY');
        $fieldDescription = Text::_('PLG_SYSTEM_NOTIFYNEWCONTENT_DESC');
        $textYes = Text::_('PLG_SYSTEM_NOTIFYNEWCONTENT_OPTION_YES');
        $textNo = Text::_('PLG_SYSTEM_NOTIFYNEWCONTENT_OPTION_NO');

        // Permissions array to allow registered users to edit their profile field
        $permissions = json_encode(array(
            'core.edit.value' => array(
                1 => 1 // Allow 'Registered' group (ID: 2) to edit the field
            )
        ));

        // Check if the field already exists
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('name') . ' = ' . $db->quote('notify-on-new-content'));
        $db->setQuery($query);

        if (!$db->loadResult())
        {
            Log::add('Profile field "notify-on-new-content" does not yet exist, creating new field', Log::DEBUG, 'install');
            // Add the field
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__fields'))
                ->columns(array(
                    $db->quoteName('title'),
                    $db->quoteName('name'),
                    $db->quoteName('label'),
                    $db->quoteName('type'),
                    $db->quoteName('context'),
                    $db->quoteName('state'),
                    $db->quoteName('fieldparams'),
                    $db->quoteName('created_time'),
                    $db->quoteName('created_user_id'),
                    $db->quoteName('modified_time'),
                    $db->quoteName('description'),
                    $db->quoteName('params'),
                    $db->quoteName('language'),
                    $db->quoteName('access'),
                    $db->quoteName('default_value')
                ))
                ->values(
                    $db->quote($fieldTitle) . ', ' .
                    $db->quote('notify-on-new-content') . ', ' .
                    $db->quote($fieldTitle) . ', ' .
                    $db->quote('radio') . ', ' .
                    $db->quote('com_users.user') . ', ' .
                    1 . ', ' .
                    $db->quote('{"options":{"options0":{"name":"' . $textYes . '","value":"1"},"options1":{"name":"' . $textNo . '","value":"0"}}}') . ', ' .
                    $db->quote(Factory::getDate()->toSql()) . ', ' .
                    $db->quote(Factory::getUser()->id) . ', ' .
                    $db->quote(Factory::getDate()->toSql()) . ', ' .
                    $db->quote($fieldDescription) . ', ' .
                    $db->quote('{"hint":"","class":"","label_class":"","show_on":"","showon":"","render_class":"","value_render_class":"","showlabel":"1","label_render_class":"","display":"2","prefix":"","suffix":"","layout":"","display_readonly":"2","searchindex":"0"}') . ', ' .
                    $db->quote('*') . ', ' .
                    $db->quote(2) . ', ' .
                    $db->quote('1')
                );

            $db->setQuery($query);
            try {
                $db->execute();
                Log::add('Profile field "notify-on-new-content" created successfully', Log::DEBUG, 'install');
                echo 'Profile field "notify-on-new-content" created successfully!';
            } catch (Exception $e) {
                Log::add('Error creating profile field "notify-on-new-content": ' . $e->getMessage(), Log::ERROR, 'install');
                echo 'Error creating profile field "notify-on-new-content"!';
            }
        } else {
            Log::add('Profile field already "notify-on-new-content" exists', Log::DEBUG, 'install');
            echo 'Profile field already "notify-on-new-content" exists!';
        }
    }

    protected function removeProfileField()
    {
        Log::add('Removing profile field "notify-on-new-content"', Log::DEBUG, 'install');
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__fields'))
            ->where($db->quoteName('name') . ' = ' . $db->quote('notify-on-new-content'));

        $db->setQuery($query);
        try {
            $db->execute();
            Log::add('Field removed successfully', Log::DEBUG, 'install');
        } catch (Exception $e) {
            Log::add('Error removing field: ' . $e->getMessage(), Log::ERROR, 'install');
        }
    }
}
