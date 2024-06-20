# Events

WoltLab Suite's event system allows manipulation of program flows and data without having to change any of the original source code.
At many locations throughout the PHP code of WoltLab Suite Core and mainly through inheritance also in the applications and plugins, so called *events* are fired which trigger registered *event listeners* that get access to the object firing the event (or at least the class name if the event has been fired in a static method).

This page focuses on the technical aspects of events and event listeners, [the eventListener package installation plugin page](../../package/pip/event-listener.md) covers how you can actually register an event listener.
A comprehensive list of all available events is provided [here](event_list.md).


## Introductory Example

Let's start with a simple example to illustrate how the event system works.
Consider this pre-existing class:

{jinja{ codebox(
  title="files/lib/system/example/ExampleComponent.class.php",
  language="php",
  filepath="php/api/events/ExampleComponent.class.php"
) }}

where an event with event name `getVar` is fired in the `getVar()` method.

If you create an object of this class and call the `getVar()` method, the return value will be `1`, of course:

```php
<?php

$example = new wcf\system\example\ExampleComponent();
if ($example->getVar() == 1) {
	echo "var is 1!";
}
else if ($example->getVar() == 2) {
	echo "var is 2!";
}
else {
	echo "No, var is neither 1 nor 2.";
}

// output: var is 1!
```

Now, consider that we have registered the following event listener to this event:

{jinja{ codebox(
  title="files/lib/system/event/listener/ExampleEventListener.class.php",
  language="php",
  filepath="php/api/events/ExampleEventListener.class.php"
) }}

Whenever the event in the `getVar()` method is called, this method (of the same event listener object) is called.
In this case, the value of the method's first parameter is the `ExampleComponent` object passed as the first argument of the `EventHandler::fireAction()` call in `ExampleComponent::getVar()`.
As `ExampleComponent::$var` is a public property, the event listener code can change it and set it to `2`.

If you now execute the example code from above again, the output will change from `var is 1!` to `var is 2!` because prior to returning the value, the event listener code changes the value from `1` to `2`.

This introductory example illustrates how event listeners can change data in a non-intrusive way.
Program flow can be changed, for example, by throwing a `wcf\system\exception\PermissionDeniedException` if some additional constraint to access a page is not fulfilled.


## Listening to Events

In order to listen to events, you need to register the event listener and the event listener itself needs to implement the interface `wcf\system\event\listener\IParameterizedEventListener` which only contains the `execute` method (see example above).

The first parameter `$eventObj` of the method contains the passed object where the event is fired or the name of the class in which the event is fired if it is fired from a static method.
The second parameter `$className` always contains the name of the class where the event has been fired.
The third parameter `$eventName` provides the name of the event within a class to uniquely identify the exact location in the class where the event has been fired.
The last parameter `$parameters` is a reference to the array which contains additional data passed by the method firing the event.
If no additional data is passed, `$parameters` is empty.


## Firing Events

If you write code and want plugins to have access at certain points, you can fire an event on your own.
The only thing to do is to call the `wcf\system\event\EventHandler::fireAction($eventObj, $eventName, array &$parameters = [])` method and pass the following parameters:

1. `$eventObj` should be `$this` if you fire from an object context, otherwise pass the class name `static::class`.
2. `$eventName` identifies the event within the class and generally has the same name as the method.
   In cases, were you might fire more than one event in a method, for example before and after a certain piece of code, you can use the prefixes `before*` and `after*` in your event names.
3. `$parameters` is an optional array which allows you to pass additional data to the event listeners without having to make this data accessible via a property explicitly only created for this purpose.
   This additional data can either be just additional information for the event listeners about the context of the method call or allow the event listener to manipulate local data if the code, where the event has been fired, uses the passed data afterwards.  

### Example: Using `$parameters` argument

Consider the following method which gets some text that the methods parses.

{jinja{ codebox(
  title="files/lib/system/example/ExampleParser.class.php",
  language="php",
  filepath="php/api/events/ExampleParser1.class.php"
) }}

After the default parsing by the method itself, the author wants to enable plugins to do additional parsing and thus fires an event and passes the parsed text as an additional parameter.
Then, a plugin can deliver the following event listener

{jinja{ codebox(
  title="files/lib/system/event/listener/ExampleParserEventListener.class.php",
  language="php",
  filepath="php/api/events/ExampleParserEventListener.class.php"
) }}

which can access the text via `$parameters['text']`.

This example can also be perfectly used to illustrate how to name multiple events in the same method.
Let's assume that the author wants to enable plugins to change the text before and after the method does its own parsing and thus fires two events:

{jinja{ codebox(
  title="files/lib/system/example/ExampleParser.class.php",
  language="php",
  filepath="php/api/events/ExampleParser2.class.php"
) }}


## Advanced Example: Additional Form Field

One common reason to use event listeners is to add an additional field to a pre-existing form (in combination with template listeners, which we will not cover here).
We will assume that users are able to do both, create and edit the objects via this form.
The points in the program flow of [AbstractForm](../pages.md#abstractform) that are relevant here are:

- adding object (after the form has been submitted):
  1. reading the value of the field
  2. validating the read value
  3. saving the additional value after successful validation and resetting locally stored value or assigning the current value of the field to the template after unsuccessful validation

- editing object:
  - on initial form request:
    1. reading the pre-existing value of the edited object
    2. assigning the field value to the template
  - after the form has been submitted:
    1. reading the value of the field
    2. validating the read value
    3. saving the additional value after successful validation
    4. assigning the current value of the field to the template

All of these cases can be covered the by following code in which we assume that `wcf\form\ExampleAddForm` is the form to create example objects and that `wcf\form\ExampleEditForm` extends `wcf\form\ExampleAddForm` and is used for editing existing example objects.

{jinja{ codebox(
  title="files/lib/system/event/listener/ExampleAddFormListener.class.php",
  language="php",
  filepath="php/api/events/ExampleAddFormListener.class.php"
) }}

The `execute` method in this example just delegates the call to a method with the same name as the event so that this class mimics the structure of a form class itself.
The form object is passed to the methods but is only given in the method signatures as a parameter here whenever the form object is actually used.
Furthermore, the type-hinting of the parameter illustrates in which contexts the method is actually called which will become clear in the following discussion of the individual methods:

- `assignVariables()` is called for the add and the edit form and simply assigns the current value of the variable to the template.
- `readData()` reads the pre-existing value of `$var` if the form has not been submitted and thus is only relevant when editing objects which is illustrated by the explicit type-hint of `ExampleEditForm`.
- `readFormParameters()` reads the value for both, the add and the edit form.
- `save()` is, of course, also relevant in both cases but requires the form object to store the additional value in the `wcf\form\AbstractForm::$additionalFields` array which can be used if a `var` column has been added to the database table in which the example objects are stored.
- `saved()` is only called for the add form as it clears the internal value so that in the `assignVariables()` call, the default value will be assigned to the template to create an "empty" form.
  During edits, this current value is the actual value that should be shown.
- `validate()` also needs to be called in both cases as the input data always has to be validated.

Lastly, the following XML file has to be used to register the event listeners (you can find more information about how to register event listeners on [the eventListener package installation plugin page](../../package/pip/event-listener.md)):

{jinja{ codebox(
  title="eventListener.xml",
  language="xml",
  filepath="php/api/events/eventListener.xml",
) }}


## PSR-14 Events

WoltLab Suite 5.5 introduces the concept of dedicated, reusable event classes.
Any newly introduced event will receive a dedicated class, implementing the `wcf\event\IPsr14Event` interface.
These event classes may be fired from multiple locations, making them reusable to convey that a conceptual action happened, instead of a specific class doing something.
An example for using the new event system could be a user logging in:
Instead of listening on a the login form being submitted and the Facebook login action successfully running, an event `UserLoggedIn` might be fired whenever a user logs in, no matter how the login is performed.

Additionally, these dedicated event classes will benefit from full IDE support.
All the relevant values may be stored as real properties on the event object.

Event classes should not have an `Event` suffix and should be stored in an `event` namespace in a matching location.
Thus, the `UserLoggedIn` example might have a FQCN of `\wcf\event\user\authentication\UserLoggedIn`.

Event listeners for events implementing `IPsr14Event` need to follow [PSR-14](https://www.php-fig.org/psr/psr-14/), i.e. they need to be callable.
In practice, this means that the event listener class needs to implement `__invoke()`.
No interface has to be implemented in this case.

Previously:

```php
$parameters = [
    'value' => \random_int(1, 1024),
];

EventHandler::getInstance()->fireAction($this, 'valueAvailable', $parameters);
```

```php title="lib/system/event/listener/ValueDumpListener.class.php"
<?php

namespace wcf\system\event\listener;

use wcf\form\ValueForm;

final class ValueDumpListener implements IParameterizedEventListener
{
    /**
     * @inheritDoc
     * @param ValueForm $eventObj
     */
    public function execute($eventObj, $className, $eventName, array &$parameters)
    {
        var_dump($parameters['value']);
    }
}
```

Now:

```php
EventHandler::getInstance()->fire(new ValueAvailable(\random_int(1, 1024)));
```

```php title="lib/event/foo/ValueAvailable.class.php"
<?php

namespace wcf\event\foo;

use wcf\event\IPsr14Event;

final class ValueAvailable implements IPsr14Event
{
    /**
     * @var int
     */
    private $value;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function getValue(): int
    {
        return $this->value;
    }
}
```

```php title="lib/system/event/listener/ValueDumpListener.class.php"
<?php

namespace wcf\system\event\listener;

use wcf\event\foo\ValueAvailable;

final class ValueDumpListener
{
    public function __invoke(ValueAvailable $event): void
    {
        \var_dump($event->getValue());
    }
}
```

### Available PSR-14 Events

| Class | Description |
|-------|-------------|
| `wcf\event\acp\dashboard\box\BoxCollecting` | Requests the collection of boxes for the acp dashboard. |
| `wcf\event\acp\dashboard\box\PHPExtensionCollecting` | Requests the collection of PHP extensions for the system info ACP dashboard box. |
| `wcf\event\acp\dashboard\box\StatusMessageCollecting` | Requests the collection of status messages for the status message dashboard box. |
| `wcf\event\acp\menu\item\ItemCollecting` | Requests the collection of acp menu items. |
| `wcf\event\cache\CacheCleared` | Indicates that a full cache clear was performed. |
| `wcf\event\comment\CommentCreated` | Indicates that a new comment has been created. |
| `wcf\event\comment\CommentPublished` | Indicates that a new comment has been published. This can happen directly when a comment is created or be delayed if a comment has first been checked and approved by a moderator. |
| `wcf\event\comment\CommentUpdated` | Indicates that a comment has been updated. |
| `wcf\event\comment\CommentsDeleted` | Indicates that multiple comments have been deleted. |
| `wcf\event\comment\response\ResponseCreated` | Indicates that a new comment response has been created. |
| `wcf\event\comment\response\ResponsePublished` | Indicates that a new comment response has been published. This can happen directly when a comment is created or be delayed if a response has first been checked and approved by a moderator. |
| `wcf\event\comment\response\ResponseUpdated` | Indicates that a response has been updated. |
| `wcf\event\comment\response\ResponsesDeleted` | Indicates that multiple responses have been deleted. |
| `wcf\event\endpoint\ControllerCollecting` | Collects the list of API controllers. |
| `wcf\event\language\LanguageContentCopying` | Indicates that the contents of a language should be copied to another one. |
| `wcf\event\language\LanguageImported` | Indicates that a language was created or updated through a manual import. |
| `wcf\event\language\PhraseChanged` | Indicates that a phrase has been modified by the user. |
| `wcf\event\language\PreloadPhrasesCollecting` | Requests the collection of phrases that should be included in the preload cache. |
| `wcf\event\message\MessageSpamChecking` | Indicates that a new message by a user is currently validated. If this event is interrupted, the message is considered to be spam. |
| `wcf\event\moderation\queue\UserAssigned` | Indicates that a user was assigned or reassigned to a moderation queue entry. |
| `wcf\event\package\PackageInstallationPluginSynced` | Indicates that the a package installation plugin was executed through the developer tools. |
| `wcf\event\package\PackageListChanged` | Indicates that the there have been changes to the package list. These changes include the installation, removal or update of existing packages. The event is fired at the end of the overall process and not for each package that has been modified. |
| `wcf\event\package\PackageUpdateListChanged` | Indicates that the there have been changes to the package update list. |
| `wcf\event\page\ContactFormSpamChecking` | Indicates that a new contact form message is currently validated. If this event is interrupted, the message is considered to be spam. |
| `wcf\event\request\ActivePageResolving` | Indicates that the `RequestHandler` could not determine the active page. |
| `wcf\event\session\PreserveVariablesCollecting` | This event allows the configuration of session variables that are to be preserved when the user changes. |
| `wcf\event\spider\SpiderCollecting` | Requests the collection of spiders. |
| `wcf\event\user\RegistrationSpamChecking` | Indicates that a registration by a new user is currently validated. If $matches is not empty, the registration is considered to be a spammer or an undesirable user. |
| `wcf\event\user\UsernameValidating` | Indicates that a username is currently validated. If this event is interrupted, the username is considered to be invalid. This event will not be fired for usernames changed by an administrator. |
| `wcf\event\user\authentication\UserLoggedIn` | Indicates that the user actively logged in, i.e. that a user change happened in response to a user's request based off proper authentication. This event specifically must not be used if the active user is changed for technical reasons, e.g. when switching back to the real user after executing some logic with guest permissions. |
| `wcf\event\user\authentication\configuration\ConfigurationLoading` | Indicates the loading of the user auth configuration. |
| `wcf\event\user\menu\item\IconResolving` | Resolves the icon of a user menu item. |
| `wcf\event\worker\RebuildWorkerCollecting` | Requests the collection of workers that should be included in the list of rebuild workers. |
