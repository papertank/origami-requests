<?php namespace Origami\Requests;

use Illuminate\Http\Request as HttpRequest;
use Illuminate\Routing\Route;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Container\Container;
use Illuminate\Validation\Validator;
use Origami\Requests\Exceptions\RequestPermissionException;
use Origami\Requests\Exceptions\HttpResponseException;

abstract class Request extends HttpRequest {
    /**
     * The container instance.
     *
     * @var  Container  $container
     */
    protected $container;

    /**
     * The route instance
     *
     * @var Route  $route
     */
    protected $route;

    /**
     * The redirector instance.
     *
     * @var Redirector
     */
    protected $redirector;

    /**
     * The URI to redirect to if validation fails.
     *
     * @var string
     */
    protected $redirect;

    /**
     * The route to redirect to if validation fails.
     *
     * @var string
     */
    protected $redirectRoute;

    /**
     * The controller action to redirect to if validation fails.
     *
     * @var string
     */
    protected $redirectAction;

    /**
     * The input keys that should not be flashed on redirect.
     *
     * @var array
     */
    protected $dontFlash = ['password', 'password_confirmation'];

    /**
     * Validate the class instance.
     *
     * @return void
     */
    public function validate()
    {
        $instance = $this->getValidatorInstance();

        if ( ! $instance->passes() )
        {
            $this->failedValidation($instance);
        }
        elseif ( ! $this->passesAuthorization() )
        {
            $this->failedAuthorization();
        }
    }

    /**
     * Get the validator instance for the request.
     *
     * @return \Illuminate\Validation\Validator
     */
    protected function getValidatorInstance()
    {
        $factory = $this->container->make('Illuminate\Validation\Factory');

        if (method_exists($this, 'validator'))
        {
            return call_user_func_array([$this, 'validator'], compact('factory'));
        }
        else
        {
            return $factory->make(
                $this->formatInput(), $this->rules(), $this->messages()
            );
        }
    }

    /**
     * Get the input that should be fed to the validator.
     *
     * @return array
     */
    protected function formatInput()
    {
        return $this->all();
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->response(
            $this->formatErrors($validator)
        ));
    }

    /**
     * Determine if the request passes the authorization check.
     *
     * @return bool
     */
    protected function passesAuthorization()
    {
        if (method_exists($this, 'authorize'))
        {
            return call_user_func([$this, 'authorize']);
        }

        return false;
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return mixed
     */
    protected function failedAuthorization()
    {
        throw new RequestPermissionException;
    }

    /**
     * Get the proper failed validation response for the request.
     *
     * @param  array  $errors
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function response(array $errors)
    {
        if ($this->ajax())
        {
            return new JsonResponse($errors, 422);
        }
        else
        {
            return $this->redirector->to($this->getRedirectUrl())
                ->withInput($this->except($this->dontFlash))
                ->withErrors($errors);
        }
    }

    /**
     * Get the response for a forbidden operation.
     *
     * @return \Illuminate\Http\Response
     */
    public function forbiddenResponse()
    {
        return new Response('Forbidden', 403);
    }

    /**
     * Format the errors from the given Validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return array
     */
    protected function formatErrors(Validator $validator)
    {
        return $validator->errors()->getMessages();
    }

    /**
     * Get the URL to redirect to on a validation error.
     *
     * @return string
     */
    protected function getRedirectUrl()
    {
        $url = $this->redirector->getUrlGenerator();

        if ($this->redirect)
        {
            return $url->to($this->redirect);
        }
        elseif ($this->redirectRoute)
        {
            return $url->route($this->redirectRoute);
        }
        elseif ($this->redirectAction)
        {
            return $url->action($this->redirectAction);
        }
        else
        {
            return $url->previous();
        }
    }

    public function setRedirector(Redirector $redirector)
    {
        $this->redirector = $redirector;

        return $this;
    }

    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    public function setRoute(Route $route)
    {
        $this->route = $route;

        return $this;
    }

    public function __get($key)
    {
        return $this->input($key);
    }

    /**
     * Set custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [];
    }

    public abstract function rules();

}
