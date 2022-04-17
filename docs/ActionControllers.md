# Action controllers

Action controllers are used to group related HTTP request handling logic into a class and use HTTP methods to separate
concerns. An action controller is created by extending the [Controller][] class and using [ActionTrait][].

An action controller can be created easily by extending [ControllerAbstract][] and using the trait [ActionTrait][].

```php
<?php

use ICanBoogie\Routing\ControllerAbstract;
use ICanBoogie\Routing\Controller\ActionTrait;

final class ArticleController extends ControllerAbstract
{
    use ActionTrait;
}
```

Next, we define the methods the controllers needs to handle. For instance, for a route with the action `articles:show`,
one the following methods can be implemented. `{method}` is a placeholder for the method of the request e.g. `get`
or `post`.

- `{method}_articles_show`
- `any_articles_show`
- `articles_show`
- `{method}_show`
- `any_show`
- `show`

Since our controller deals exclusively with articles, let's go with the simplest one: `show`. It's recommended
to is the `private` visibility.

```php
<?php

use ICanBoogie\Routing\ControllerAbstract;
use ICanBoogie\Routing\Controller\ActionTrait;

final class ArticleController extends ControllerAbstract
{
    use ActionTrait;

    private function show(): string
    {
        // …
    }
}
```

## Use case: A contact form

The following example demonstrates how an action controller can be used to display a contact form, handle its
submission, and redirect the user to a _success_ page. The action invoked inside the controller is defined after the "#"
character. The action may as well be defined using the `action` key.

```php
<?php

use ICanBoogie\Routing\Route;

$route = new Route('/contact', 'contact');
```

The HTTP method is used as a prefix for the method handling the action. The prefix "any" is used
for methods that handle any kind of HTTP method, they are a fallback when more accurate methods
are not available. If you don't care about that, you can omit the HTTP method.

```php
<?php

use ICanBoogie\Routing\Controller;

class AppController extends Controller
{
    use Controller\ActionTrait;

    private readonly ContactForm $form;

    protected function any_contact()
    {
        return $this->form;
    }

    protected function post_contact(Request $request)
    {
        if (!$this->form->validate($request->params, $errors))
        {
            return $this->redirect('contact');
        }

        // …

        $email = $request['email'];
        $message = $request['message'];

        // …
    }
}
```
