<?php
// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Log\Log;

/// @brief Allows the user to receive optional notification emails when new content is posted on the website.
class PlgSystemNotifynewcontent extends CMSPlugin
{
    protected $app; ///< App
    protected $db; ///< Database

    /// @brief Constructor
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->db = Factory::getDbo();

        // Load the plugin language file
        $lang = Factory::getLanguage();
        $lang->load('plg_system_notifynewcontent', JPATH_ADMINISTRATOR, null, true);
    } // public function __construct(&$subject, $config)


    /// @brief Injects a field into the article form. Used to make the "Notify users" option appear.
    public function onContentPrepareForm($form, $data)
    {
        Log::add('onContentPrepareForm triggered with form->getName() == ' . $form->getName(), Log::DEBUG, 'notifynewcontent');
        if ($form->getName() == 'com_content.article')
        {
            // Is category monitored?
            $articleCategory = isset($data->catid) ? $data->catid : JFactory::getApplication()->input->get('catid', 0);
            $monitorCategory = $this->params->get('monitor_category', 0);
            Log::add('$articleCategory: ' . $articleCategory, Log::DEBUG, 'notifynewcontent');
            Log::add('$monitorCategory: ' . $monitorCategory, Log::DEBUG, 'notifynewcontent');
            $isMonitoredCategory = $this->isInTargetCategory($articleCategory, $monitorCategory);
            Log::add('$isMonitoredCategory: ' . $isMonitoredCategory, Log::DEBUG, 'notifynewcontent');

            // Load the form field XML
            // TODO: How can we make the checkbox appear in the "Content" group, right next to the category selector??
            $form->load('<form><fields name="attribs"><fieldset name="notify"><field name="notify-on-publish" type="checkbox" default="' . ($isMonitoredCategory ? '1' : '0') . '" label="PLG_SYSTEM_NOTIFYNEWCONTENT_SEND_NOTIFICATION_LABEL" description="PLG_SYSTEM_NOTIFYNEWCONTENT_SEND_NOTIFICATION_DESC"/></fieldset></fields></form>');

            // Set the default value based on category

            // Default state of the checkbox
            $form->setFieldAttribute('notify-on-publish', 'checked', $isMonitoredCategory ? 'checked' : '');
            $form->setValue('notify-on-publish', 'attribs', $isMonitoredCategory ? 1 : 0);

            return true;
        } // if ($form->getName() == 'com_content.article')
    } // public function onContentPrepareForm($form, $data)


    /// @brief Called after an article has been saved (new or edited). If the article is in a monitored category, notification mails will be sent.
    public function onContentAfterSave($context, $article, $isNew)
    {
        Log::add('onContentAfterSave triggered with context: ' . $context, Log::DEBUG, 'notifynewcontent');
        
        // Check if the checkbox was checked
        $input = JFactory::getApplication()->input;
        $sendNotification = $input->get('jform', array(), 'array');
        $sendNotification = isset($sendNotification['attribs']['notify-on-publish']) ? (bool)$sendNotification['attribs']['notify-on-publish'] : false;
        
        if ($sendNotification && /* $this->isMonitoredEvent($isNew) &&*/ $this->isMonitoredContext($context))
        {
            Log::add('New article "' . $article->title . '" detected', Log::DEBUG, 'notifynewcontent');
            $targetCategoryId = (int) $this->params->get('monitor_category');
            Log::add('Monitoring category: "' . $targetCategoryId, Log::DEBUG, 'notifynewcontent');
            if ($article && $this->isInTargetCategory($article->catid, $targetCategoryId))
            {
                $this->notifyUsers($article, $targetCategoryId, $isNew);
            }
        }
    } // public function onContentAfterSave($context, $article, $isNew)


    /// @brief Returns `true` if we are within a monitored context (`com_content.article` for articles in the backend, or `com_content.form` for the frontend article form).
    protected function isMonitoredContext($context)
    {
        return $context === 'com_content.article' || $context === 'com_content.form';
    } // protected function isMonitoredContext($context)


    /// @brief Returns `true` if `$catid` is the same as, or a child of, `$targetCategoryId`.
    protected function isInTargetCategory($catid, $targetCategoryId)
    {
        // Check if the category is the target category or one of its subcategories
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $catid)
            ->where(
                '('. $this->db->quoteName('id') . ' = ' . (int) $targetCategoryId . 
                ' OR ' . $this->db->quoteName('parent_id') . ' = ' . (int) $targetCategoryId . 
                ' OR ' . $this->db->quoteName('path') . ' LIKE ' . $this->db->quote('%/' . $targetCategoryId . '/%') . ')'
            );
        $this->db->setQuery($query);
        $result = $this->db->loadResult();

        // Log / debug messages
        $categoryMsg = 'isInTargetCategory(): $catid=' . $catid . ', $targetCategoryId=' . $targetCategoryId . ' -> ' . $result;
        // if ($result)
        //    JFactory::getApplication()->enqueueMessage($categoryMsg, 'message');
        Log::add($categoryMsg, Log::DEBUG, 'notifynewcontent');

        return (bool) $result;
    } // protected function isInTargetCategory($catid, $targetCategoryId)


    /// @brief Queries the database to get a list of users that have enabled the `notify-on-new-content` option in their profile, and sends a notification email to each of them.
    protected function notifyUsers($article, $targetCategoryId, $isNew)
    {
        // Get the field ID for 'notify-on-new-content'
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__fields'))
            ->where($this->db->quoteName('name') . ' = ' . $this->db->quote('notify-on-new-content'));
        $this->db->setQuery($query);
        $fieldId = $this->db->loadResult();

        if (!$fieldId)
        {
            Log::add('Field "notify-on-new-content" not found.', Log::ERROR, 'notifynewcontent');
            return;
        }

        // Get all users with the "Notify on new content" enabled
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('u.email', 'u.name')))
            ->from($this->db->quoteName('#__users', 'u'))
            ->join('INNER', $this->db->quoteName('#__fields_values', 'fv') . ' ON ' . $this->db->quoteName('u.id') . ' = ' . $this->db->quoteName('fv.item_id'))
            ->where($this->db->quoteName('fv.field_id') . ' = ' . (int) $fieldId)
            ->where($this->db->quoteName('fv.value') . ' = ' . $this->db->quote('1'));

        $this->db->setQuery($query);
        $users = $this->db->loadObjectList();
        $userCount = count($users);

        if ($userCount == 0)
        {
            $notDoingItMsg = 'No users with enabled notification option found. Will not send any mails';
            Log::add($notDoingItMsg, Log::DEBUG, 'notifynewcontent');
            return;
        }
        else
        {
            $doingItMsg = str_replace('{USER_COUNT}', $userCount, Text::_('PLG_SYSTEM_NOTIFYNEWCONTENT_MSG_SENDINGMAILS'));
            Log::add($doingItMsg, Log::DEBUG, 'notifynewcontent');
            JFactory::getApplication()->enqueueMessage($doingItMsg, 'message');
        }

        // Get the category name
        $categoryId = $article->catid;
        $categoryQuery = $this->db->getQuery(true)
            ->select($this->db->quoteName('title'))
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $categoryId);
        $this->db->setQuery($categoryQuery);
        $categoryName = $this->db->loadResult();

        // Prepare email subject and body
        Log::add('Generating mail subject and body', Log::DEBUG, 'notifynewcontent');
        $subject =  $isNew ? $this->params->get('mail_subject_new') : $this->params->get('mail_subject_edit');
        $bodyTemplate = $isNew ? $this->params->get('mail_body_new') : $this->params->get('mail_body_edit');

        // Generate URL
        Log::add('Generating article link', Log::DEBUG, 'notifynewcontent');
        $articleLink = Uri::root() . 'index.php?option=com_content&view=article&id=' . $article->id . ':' . $article->alias . '&catid=' . $article->catid;

        // Generate SEF URL
        $articleLink = Route::link('site', 'index.php?option=com_content&view=article&id=' . $article->id . ':' . $article->alias . '&catid=' . $article->catid, false);
        // Ensure no double slashes in the URL
        $articleLink = str_replace('//', '/', $articleLink);
        $articleLink = Uri::root() . ltrim($articleLink, '/');

        // Format the publish and edit dates
        $publishDate = (new \Joomla\CMS\Date\Date($article->publish_up))->format('d.m.Y');
        $modifiedDate = (new \Joomla\CMS\Date\Date($article->modified))->format('d.m.Y');

        // Get the article introtext
        $introtext = strip_tags($article->introtext);

        // Fill data into template
        Log::add('Replacing placeholders in mail body template', Log::DEBUG, 'notifynewcontent');
        $body = str_replace(
            array('{ARTICLE_TITLE}', '{ARTICLE_CATEGORY}', '{ARTICLE_PUBLISH_DATE}', '{ARTICLE_MODIFIED_DATE}', '{ARTICLE_LINK}', '{ARTICLE_INTROTEXT}'),
            array($article->title, $categoryName, $publishDate, $modifiedDate, $articleLink, $introtext),
            $bodyTemplate
        );
        Log::add('Email body: ' . $body, Log::DEBUG, 'notifynewcontent');

        // Send email to each user
        foreach ($users as $user)
        {
            Log::add('Sending mail to user ' . $user->name, Log::DEBUG, 'notifynewcontent');
            $mail = Factory::getMailer();
            $mail->addRecipient($user->email);
            $mail->setSubject($subject);
            $mail->setBody(str_replace('{USERNAME}', $user->name, $body));
            $mail->Send();

            Log::add('Email sent to: ' . $user->email, Log::DEBUG, 'notifynewcontent');
        } // foreach ($users as $user)
    } // protected function notifyUsers($article, $targetCategoryId)

} // class PlgSystemNotifynewcontent
