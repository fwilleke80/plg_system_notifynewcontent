<?php
// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Log\Log;

class PlgSystemNotifynewcontent extends CMSPlugin
{
    protected $app;
    protected $db;

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->db = Factory::getDbo();

        // Load the plugin language file
        $lang = Factory::getLanguage();
        $lang->load('plg_system_notifynewcontent', JPATH_ADMINISTRATOR, null, true);
    }

    public function onContentAfterSave($context, $article, $isNew)
    {
        Log::add('onContentAfterSave triggered with context: ' . $context, Log::DEBUG, 'notifynewcontent');

        if ($this->isMonitoredEvent($isNew) && $this->isMonitoredContext($context))
        {
            Log::add('New article "' . $article->title . '" detected', Log::DEBUG, 'notifynewcontent');
            $targetCategoryId = (int) $this->params->get('monitor_category');
            Log::add('Monitoring category: "' . $targetCategoryId, Log::DEBUG, 'notifynewcontent');
            if ($article && $this->isInTargetCategory($article->catid, $targetCategoryId))
            {
                $this->notifyUsers($article, $targetCategoryId);
            }
        }
    }

    protected function isMonitoredEvent($isNew)
    {
        return $isNew || $this->params->get('notify_on_edit');
    }

    protected function isMonitoredContext($context)
    {
        return $context === 'com_content.article' || $context === 'com_content.form';
    }

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

        $categoryMsg = str_replace('{CATEGORYCHECK_RESULT}', $result ? Text::_('PLG_SYSTEM_NOTIFYNEWCONTENT_MSG_CHECKCATEGORY_TRUE') : 'Article posted in non-notification category. Won\'t send any mails.', str_replace('{ARTICLE_CATEGORY}', $catid, Text::_('PLG_SYSTEM_NOTIFYNEWCONTENT_MSG_CHECKCATEGORY')));
        if ($result)
        {
            JFactory::getApplication()->enqueueMessage($categoryMsg, 'message');
        }

        Log::add($categoryMsg, Log::DEBUG, 'notifynewcontent');

        return (bool) $result;
    }

    protected function notifyUsers($article, $targetCategoryId)
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
        $subject = $this->params->get('mail_subject');
        $bodyTemplate = $this->params->get('mail_body');

        // Generate URL
        Log::add('Generating article link', Log::DEBUG, 'notifynewcontent');
        $articleLink = Uri::root() . 'index.php?option=com_content&view=article&id=' . $article->id . ':' . $article->alias . '&catid=' . $article->catid;

        // Generate SEF URL
        $articleLink = Route::link('site', 'index.php?option=com_content&view=article&id=' . $article->id . ':' . $article->alias . '&catid=' . $article->catid, false);
        // Ensure no double slashes in the URL
        $articleLink = str_replace('//', '/', $articleLink);
        $articleLink = Uri::root() . ltrim($articleLink, '/');

        // Format the publish date
        $publishDate = (new \Joomla\CMS\Date\Date($article->publish_up))->format('d.m.Y');

        // Get the article introtext
        $introtext = strip_tags($article->introtext);

        // Fill data into template
        Log::add('Replacing placeholders in mail body template', Log::DEBUG, 'notifynewcontent');
        $body = str_replace(
            array('{ARTICLE_TITLE}', '{ARTICLE_CATEGORY}', '{ARTICLE_PUBLISH_DATE}', '{ARTICLE_LINK}', '{ARTICLE_INTROTEXT}'),
            array($article->title, $categoryName, $publishDate, $articleLink, $introtext),
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
        }
    }
}
