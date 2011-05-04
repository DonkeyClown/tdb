<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Gdata
 * @subpackage Demos
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/**
 * PHP sample code for the Google Calendar data API.  Utilizes the
 * Zend Framework Gdata components to communicate with the Google API.
 *
 * Requires the Zend Framework Gdata components and PHP >= 5.1.4
 *
 * You can run this sample both from the command line (CLI) and also
 * from a web browser.  When running through a web browser, only
 * AuthSub and outputting a list of calendars is demonstrated.  When
 * running via CLI, all functionality except AuthSub is available and dependent
 * upon the command line options passed.  Run this script without any
 * command line options to see usage, eg:
 *     /usr/local/bin/php -f Calendar.php
 *
 * More information on the Command Line Interface is available at:
 *     http://www.php.net/features.commandline
 *
 * NOTE: You must ensure that the Zend Framework is in your PHP include
 * path.  You can do this via php.ini settings, or by modifying the
 * argument to set_include_path in the code below.
 *
 * NOTE: As this is sample code, not all of the functions do full error
 * handling.  Please see getEvent for an example of how errors could
 * be handled and the online code samples for additional information.
 */

/**
 * @see Zend_Loader
 */
require_once 'Zend/Loader.php';

/**
 * @see Zend_Gdata
 */
Zend_Loader::loadClass('Zend_Gdata');

/**
 * @see Zend_Gdata_AuthSub
 */
Zend_Loader::loadClass('Zend_Gdata_AuthSub');

/**
 * @see Zend_Gdata_ClientLogin
 */
Zend_Loader::loadClass('Zend_Gdata_ClientLogin');

/**
 * @see Zend_Gdata_HttpClient
 */
Zend_Loader::loadClass('Zend_Gdata_HttpClient');

/**
 * @see Zend_Gdata_Calendar
 */
Zend_Loader::loadClass('Zend_Gdata_Calendar');

/**
 * @var string Location of AuthSub key file.  include_path is used to find this
 */
$_authSubKeyFile = null; // Example value for secure use: 'mykey.pem'

/**
 * @var string Passphrase for AuthSub key file.
 */
$_authSubKeyFilePassphrase = null;

/**
 * Returns the full URL of the current page, based upon env variables
 *
 * Env variables used:
 * $_SERVER['HTTPS'] = (on|off|)
 * $_SERVER['HTTP_HOST'] = value of the Host: header
 * $_SERVER['SERVER_PORT'] = port number (only used if not http/80,https/443)
 * $_SERVER['REQUEST_URI'] = the URI after the method of the HTTP request
 *
 * @return string Current URL
 */
function getCurrentUrl()
{
  global $_SERVER;

  /**
   * Filter php_self to avoid a security vulnerability.
   */
  $php_request_uri = htmlentities(substr($_SERVER['REQUEST_URI'], 0, strcspn($_SERVER['REQUEST_URI'], "\n\r")), ENT_QUOTES);

  if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') {
    $protocol = 'https://';
  } else {
    $protocol = 'http://';
  }
  $host = $_SERVER['HTTP_HOST'];
  if ($_SERVER['SERVER_PORT'] != '' &&
     (($protocol == 'http://' && $_SERVER['SERVER_PORT'] != '80') ||
     ($protocol == 'https://' && $_SERVER['SERVER_PORT'] != '443'))) {
    $port = ':' . $_SERVER['SERVER_PORT'];
  } else {
    $port = '';
  }
  return $protocol . $host . $port . $php_request_uri;
}

/**
 * Returns the AuthSub URL which the user must visit to authenticate requests
 * from this application.
 *
 * Uses getCurrentUrl() to get the next URL which the user will be redirected
 * to after successfully authenticating with the Google service.
 *
 * @return string AuthSub URL
 */
function getAuthSubUrl()
{
  global $_authSubKeyFile;
  $next = getCurrentUrl();
  $scope = 'http://www.google.com/calendar/feeds/';
  $session = true;
  if ($_authSubKeyFile != null) {
    $secure = true;
  } else {
    $secure = false;
  }
  return Zend_Gdata_AuthSub::getAuthSubTokenUri($next, $scope, $secure,
      $session);
}

/**
 * Outputs a request to the user to login to their Google account, including
 * a link to the AuthSub URL.
 *
 * Uses getAuthSubUrl() to get the URL which the user must visit to authenticate
 *
 * @return void
 */
function requestUserLogin($linkText)
{
  $authSubUrl = getAuthSubUrl();
  echo "<a href=\"{$authSubUrl}\">{$linkText}</a>";
}

/**
 * Returns a HTTP client object with the appropriate headers for communicating
 * with Google using AuthSub authentication.
 *
 * Uses the $_SESSION['sessionToken'] to store the AuthSub session token after
 * it is obtained.  The single use token supplied in the URL when redirected
 * after the user succesfully authenticated to Google is retrieved from the
 * $_GET['token'] variable.
 *
 * @return Zend_Http_Client
 */
function getAuthSubHttpClient()
{
  global $_SESSION, $_GET, $_authSubKeyFile, $_authSubKeyFilePassphrase;
  $client = new Zend_Gdata_HttpClient();
  if ($_authSubKeyFile != null) {
    // set the AuthSub key
    $client->setAuthSubPrivateKeyFile($_authSubKeyFile, $_authSubKeyFilePassphrase, true);
  }
  if (!isset($_SESSION['sessionToken']) && isset($_GET['token'])) {
    $_SESSION['sessionToken'] =
        Zend_Gdata_AuthSub::getAuthSubSessionToken($_GET['token'], $client);
  }
  $client->setAuthSubToken($_SESSION['sessionToken']);
  return $client;
}

/**
 * Processes loading of this sample code through a web browser.  Uses AuthSub
 * authentication and outputs a list of a user's calendars if succesfully
 * authenticated.
 *
 * @return void
 */
function processPageLoad()
{
  global $_SESSION, $_GET;
  if (!isset($_SESSION['sessionToken']) && !isset($_GET['token'])) {
    requestUserLogin('Please login to your Google Account.');
  } else {
    $client = getAuthSubHttpClient();
    outputCalendarList($client);
  }
}

/**
 * Returns a HTTP client object with the appropriate headers for communicating
 * with Google using the ClientLogin credentials supplied.
 *
 * @param  string $user The username, in e-mail address format, to authenticate
 * @param  string $pass The password for the user specified
 * @return Zend_Http_Client
 */
function getClientLoginHttpClient($user, $pass)
{
  $service = Zend_Gdata_Calendar::AUTH_SERVICE_NAME;

  $client = Zend_Gdata_ClientLogin::getHttpClient($user, $pass, $service);
  return $client;
}

/**
 * Outputs an HTML unordered list (ul), with each list item representing an event
 * in the user's calendar.  The calendar is retrieved using the magic cookie
 * which allows read-only access to private calendar data using a special token
 * available from within the Calendar UI.
 *
 * @param  string $user        The username or address of the calendar to be retrieved.
 * @param  string $magicCookie The magic cookie token
 * @return void
 */
function outputCalendarMagicCookie($user, $magicCookie)
{
  $gdataCal = new Zend_Gdata_Calendar();
  $query = $gdataCal->newEventQuery();
  $query->setUser($user);
  $query->setVisibility('private-' . $magicCookie);
  $query->setProjection('full');
  $eventFeed = $gdataCal->getCalendarEventFeed($query);
  echo "<ul>\n";
  foreach ($eventFeed as $event) {
    echo "\t<li>" . $event->title->text . "</li>\n";
    $sl = $event->getLink('self')->href;
  }
  echo "</ul>\n";
}

/**
 * Outputs an HTML unordered list (ul), with each list item representing a
 * calendar in the authenticated user's calendar list.
 *
 * @param  Zend_Http_Client $client The authenticated client object
 * @return void
 */
function outputCalendarList($client)
{
  $gdataCal = new Zend_Gdata_Calendar($client);
  $calFeed = $gdataCal->getCalendarListFeed();
  echo "<h1>" . $calFeed->title->text . "</h1>\n";
  echo "<ul>\n";
  foreach ($calFeed as $calendar) {
    echo "\t<li>" . $calendar->title->text . "</li>\n";
  }
  echo "</ul>\n";
}

/**
 * Outputs an HTML unordered list (ul), with each list item representing an
 * event on the authenticated user's calendar.  Includes the start time and
 * event ID in the output.  Events are ordered by starttime and include only
 * events occurring in the future.
 *
 * @param  Zend_Http_Client $client The authenticated client object
 * @return void
 */
function outputCalendar($client)
{
  $gdataCal = new Zend_Gdata_Calendar($client);
  $query = $gdataCal->newEventQuery();
  $query->setUser('default');
  $query->setVisibility('private');
  $query->setProjection('full');
  $query->setOrderby('starttime');
  $query->setFutureevents(true);
  $eventFeed = $gdataCal->getCalendarEventFeed($query);
  // option 2
  // $eventFeed = $gdataCal->getCalendarEventFeed($query->getQueryUrl());
  echo "<ul>\n";
  foreach ($eventFeed as $event) {
    echo "\t<li>" . $event->title->text .  " (" . $event->id->text . ")\n";
    // Zend_Gdata_App_Extensions_Title->__toString() is defined, so the
    // following will also work on PHP >= 5.2.0
    //echo "\t<li>" . $event->title .  " (" . $event->id . ")\n";
    echo "\t\t<ul>\n";
    foreach ($event->when as $when) {
      echo "\t\t\t<li>Starts: " . $when->startTime . "</li>\n";
    }
    echo "\t\t</ul>\n";
    echo "\t</li>\n";
  }
  echo "</ul>\n";
}

/**
 * Outputs an HTML unordered list (ul), with each list item representing an
 * event on the authenticated user's calendar which occurs during the
 * specified date range.
 *
 * To query for all events occurring on 2006-12-24, you would query for
 * a startDate of '2006-12-24' and an endDate of '2006-12-25' as the upper
 * bound for date queries is exclusive.  See the 'query parameters reference':
 * http://code.google.com/apis/gdata/calendar.html#Parameters
 *
 * @param  Zend_Http_Client $client    The authenticated client object
 * @param  string           $startDate The start date in YYYY-MM-DD format
 * @param  string           $endDate   The end date in YYYY-MM-DD format
 * @return void
 */
function outputCalendarByDateRange($client, $startDate='2007-05-01',
                                   $endDate='2007-08-01')
{
  $gdataCal = new Zend_Gdata_Calendar($client);
  $query = $gdataCal->newEventQuery();
  $query->setUser('default');
  $query->setVisibility('private');
  $query->setProjection('full');
  $query->setOrderby('starttime');
  $query->setStartMin($startDate);
  $query->setStartMax($endDate);
  $eventFeed = $gdataCal->getCalendarEventFeed($query);
  echo "<ul>\n";
  foreach ($eventFeed as $event) {
    echo "\t<li>" . $event->title->text .  " (" . $event->id->text . ")\n";
    echo "\t\t<ul>\n";
    foreach ($event->when as $when) {
      echo "\t\t\t<li>Starts: " . $when->startTime . "</li>\n";
    }
    echo "\t\t</ul>\n";
    echo "\t</li>\n";
  }
  echo "</ul>\n";
}

/**
 * Outputs an HTML unordered list (ul), with each list item representing an
 * event on the authenticated user's calendar which matches the search string
 * specified as the $fullTextQuery parameter
 *
 * @param  Zend_Http_Client $client        The authenticated client object
 * @param  string           $fullTextQuery The string for which you are searching
 * @return void
 */
function outputCalendarByFullTextQuery($client, $fullTextQuery='tennis')
{
  $gdataCal = new Zend_Gdata_Calendar($client);
  $query = $gdataCal->newEventQuery();
  $query->setUser('default');
  $query->setVisibility('private');
  $query->setProjection('full');
  $query->setQuery($fullTextQuery);
  $eventFeed = $gdataCal->getCalendarEventFeed($query);
  echo "<ul>\n";
  foreach ($eventFeed as $event) {
    echo "\t<li>" . $event->title->text .  " (" . $event->id->text . ")\n";
    echo "\t\t<ul>\n";
    foreach ($event->when as $when) {
      echo "\t\t\t<li>Starts: " . $when->startTime . "</li>\n";
      echo "\t\t</ul>\n";
      echo "\t</li>\n";
    }
  }
  echo "</ul>\n";
}

/**
 * Creates an event on the authenticated user's default calendar with the
 * specified event details.
 *
 * @param  Zend_Http_Client $client    The authenticated client object
 * @param  string           $title     The event title
 * @param  string           $desc      The detailed description of the event
 * @param  string           $where
 * @param  string           $startDate The start date of the event in YYYY-MM-DD format
 * @param  string           $startTime The start time of the event in HH:MM 24hr format
 * @param  string           $endDate   The end date of the event in YYYY-MM-DD format
 * @param  string           $endTime   The end time of the event in HH:MM 24hr format
 * @param  string           $tzOffset  The offset from GMT/UTC in [+-]DD format (eg -08)
 * @return string The ID URL for the event.
 */
/*function createEvent ($client, $title = 'Tennis with Beth',
    $desc='Meet for a quick lesson', $where = 'On the courts',
    $startDate = '2008-01-20', $startTime = '10:00',
    $endDate = '2008-01-20', $endTime = '11:00', $tzOffset = '-08')
{
  $gc = new Zend_Gdata_Calendar($client);
  $newEntry = $gc->newEventEntry();
  $newEntry->title = $gc->newTitle(trim($title));
  $newEntry->where  = array($gc->newWhere($where));

  $newEntry->content = $gc->newContent($desc);
  $newEntry->content->type = 'text';

  $when = $gc->newWhen();
  $when->startTime = "{$startDate}T{$startTime}:00.000{$tzOffset}:00";
  $when->endTime = "{$endDate}T{$endTime}:00.000{$tzOffset}:00";
  $newEntry->when = array($when);

  $createdEntry = $gc->insertEvent($newEntry);
  return $createdEntry->id->text;
}
*/
/**
 * Creates an event on the authenticated user's default calendar using
 * the specified QuickAdd string.
 *
 * @param  Zend_Http_Client $client       The authenticated client object
 * @param  string           $quickAddText The QuickAdd text for the event
 * @return string The ID URL for the event
 */
function createQuickAddEvent ($client, $quickAddText) {
  $gdataCal = new Zend_Gdata_Calendar($client);
  $event = $gdataCal->newEventEntry();
  $event->content = $gdataCal->newContent($quickAddText);
  $event->quickAdd = $gdataCal->newQuickAdd(true);

  $newEvent = $gdataCal->insertEvent($event);
  return $newEvent->id->text;
}

/**
 * Creates a new web content event on the authenticated user's default
 * calendar with the specified event details. For simplicity, the event
 * is created as an all day event and does not include a description.
 *
 * @param  Zend_Http_Client $client    The authenticated client object
 * @param  string           $title     The event title
 * @param  string           $startDate The start date of the event in YYYY-MM-DD format
 * @param  string           $endDate   The end time of the event in HH:MM 24hr format
 * @param  string           $icon      URL pointing to a 16x16 px icon representing the event.
 * @param  string           $url       The URL containing the web content for the event.
 * @param  string           $height    The desired height of the web content pane.
 * @param  string           $width     The desired width of the web content pane.
 * @param  string           $type      The MIME type of the web content.
 * @return string The ID URL for the event.
 */
function createWebContentEvent ($client, $title = 'World Cup 2006',
    $startDate = '2006-06-09', $endDate = '2006-06-09',
    $icon = 'http://www.google.com/calendar/images/google-holiday.gif',
    $url = 'http://www.google.com/logos/worldcup06.gif',
    $height  = '120', $width = '276', $type = 'image/gif'
    )
{
  $gc = new Zend_Gdata_Calendar($client);
  $newEntry = $gc->newEventEntry();
  $newEntry->title = $gc->newTitle(trim($title));

  $when = $gc->newWhen();
  $when->startTime = $startDate;
  $when->endTime = $endDate;
  $newEntry->when = array($when);

  $wc = $gc->newWebContent();
  $wc->url = $url;
  $wc->height = $height;
  $wc->width = $width;

  $wcLink = $gc->newLink();
  $wcLink->rel = "http://schemas.google.com/gCal/2005/webContent";
  $wcLink->title = $title;
  $wcLink->type = $type;
  $wcLink->href = $icon;

  $wcLink->webContent = $wc;
  $newEntry->link = array($wcLink);

  $createdEntry = $gc->insertEvent($newEntry);
  return $createdEntry->id->text;
}

/**
 * Creates a recurring event on the authenticated user's default calendar with
 * the specified event details.
 *
 * @param  Zend_Http_Client $client    The authenticated client object
 * @param  string           $title     The event title
 * @param  string           $desc      The detailed description of the event
 * @param  string           $where
 * @param  string           $recurData The iCalendar recurring event syntax (RFC2445)
 * @return void
 */
function createRecurringEvent ($client, $title = 'Tennis with Beth',
    $desc='Meet for a quick lesson', $where = 'On the courts',
    $recurData = null)
{
  $gc = new Zend_Gdata_Calendar($client);
  $newEntry = $gc->newEventEntry();
  $newEntry->title = $gc->newTitle(trim($title));
  $newEntry->where = array($gc->newWhere($where));

  $newEntry->content = $gc->newContent($desc);
  $newEntry->content->type = 'text';

  /**
   * Due to the length of this recurrence syntax, we did not specify
   * it as a default parameter value directly
   */
  if ($recurData == null) {
    $recurData =
        "DTSTART;VALUE=DATE:20070501\r\n" .
        "DTEND;VALUE=DATE:20070502\r\n" .
        "RRULE:FREQ=WEEKLY;BYDAY=Tu;UNTIL=20070904\r\n";
  }

  $newEntry->recurrence = $gc->newRecurrence($recurData);

  $gc->post($newEntry->saveXML());
}

/**
 * Returns an entry object representing the event with the specified ID.
 *
 * @param  Zend_Http_Client $client  The authenticated client object
 * @param  string           $eventId The event ID string
 * @return Zend_Gdata_Calendar_EventEntry|null if the event is found, null if it's not
 */
function getEvent($client, $eventId)
{
  $gdataCal = new Zend_Gdata_Calendar($client);
  $query = $gdataCal->newEventQuery();
  $query->setUser('default');
  $query->setVisibility('private');
  $query->setProjection('full');
  $query->setEvent($eventId);

  try {
    $eventEntry = $gdataCal->getCalendarEventEntry($query);
    return $eventEntry;
  } catch (Zend_Gdata_App_Exception $e) {
    var_dump($e);
    return null;
  }
}

/**
 * Updates the title of the event with the specified ID to be
 * the title specified.  Also outputs the new and old title
 * with HTML br elements separating the lines
 *
 * @param  Zend_Http_Client $client   The authenticated client object
 * @param  string           $eventId  The event ID string
 * @param  string           $newTitle The new title to set on this event
 * @return Zend_Gdata_Calendar_EventEntry|null The updated entry
 */
function updateEvent ($client, $eventId, $newTitle)
{
  $gdataCal = new Zend_Gdata_Calendar($client);
  if ($eventOld = getEvent($client, $eventId)) {
    echo "Old title: " . $eventOld->title->text . "<br />\n";
    $eventOld->title = $gdataCal->newTitle($newTitle);
    try {
        $eventOld->save();
    } catch (Zend_Gdata_App_Exception $e) {
        var_dump($e);
        return null;
    }
    $eventNew = getEvent($client, $eventId);
    echo "New title: " . $eventNew->title->text . "<br />\n";
    return $eventNew;
  } else {
    return null;
  }
}

/**
 * Adds an extended property to the event specified as a parameter.
 * An extended property is an arbitrary name/value pair that can be added
 * to an event and retrieved via the API.  It is not accessible from the
 * calendar web interface.
 *
 * @param  Zend_Http_Client $client  The authenticated client object
 * @param  string           $eventId The event ID string
 * @param  string           $name    The name of the extended property
 * @param  string           $value   The value of the extended property
 * @return Zend_Gdata_Calendar_EventEntry|null The updated entry
 */
function addExtendedProperty ($client, $eventId,
    $name='http://www.example.com/schemas/2005#mycal.id', $value='1234')
{
  $gc = new Zend_Gdata_Calendar($client);
  if ($event = getEvent($client, $eventId)) {
    $extProp = $gc->newExtendedProperty($name, $value);
    $extProps = array_merge($event->extendedProperty, array($extProp));
    $event->extendedProperty = $extProps;
    $eventNew = $event->save();
    return $eventNew;
  } else {
    return null;
  }
}


/**
 * Adds a reminder to the event specified as a parameter.
 *
 * @param  Zend_Http_Client $client  The authenticated client object
 * @param  string           $eventId The event ID string
 * @param  integer          $minutes Minutes before event to set reminder
 * @return Zend_Gdata_Calendar_EventEntry|null The updated entry
 */
function setReminder($client, $eventId, $minutes=15)
{
  $gc = new Zend_Gdata_Calendar($client);
  $method = "alert";
  if ($event = getEvent($client, $eventId)) {
    $times = $event->when;
    foreach ($times as $when) {
        $reminder = $gc->newReminder();
        $reminder->setMinutes($minutes);
        $reminder->setMethod($method);
        $when->reminder = array($reminder);
    }
    $eventNew = $event->save();
    return $eventNew;
  } else {
    return null;
  }
}

/**
 * Deletes the event specified by retrieving the atom entry object
 * and calling Zend_Feed_EntryAtom::delete() method.  This is for
 * example purposes only, as it is inefficient to retrieve the entire
 * atom entry only for the purposes of deleting it.
 *
 * @param  Zend_Http_Client $client  The authenticated client object
 * @param  string           $eventId The event ID string
 * @return void
 */
function deleteEventById ($client, $eventId)
{
  $event = getEvent($client, $eventId);
  $event->delete();
}

/**
 * Deletes the event specified by calling the Zend_Gdata::delete()
 * method.  The URL is typically in the format of:
 * http://www.google.com/calendar/feeds/default/private/full/<eventId>
 *
 * @param  Zend_Http_Client $client The authenticated client object
 * @param  string           $url    The url for the event to be deleted
 * @return void
 */
function deleteEventByUrl ($client, $url)
{
  $gdataCal = new Zend_Gdata_Calendar($client);
  $gdataCal->delete($url);
}
