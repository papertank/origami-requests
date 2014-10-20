<?php namespace Origami\Requests;

use Illuminate\Support\ServiceProvider;
use Origami\Requests\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

class RequestsServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->app['events']->listen('router.matched', function($route, $request)
        {
            $this->app->resolvingAny(function($resolved, $app) use($route, $request)
            {
                if ( $resolved instanceof Request ) {
                    $this->initializeRequest($resolved, $request);

                    $resolved->setContainer($app)
                        ->setRedirector($app['Illuminate\Routing\Redirector'])
                        ->setRoute($route);

                    $resolved->validate();
                }
            });
        });

        $this->app->error(function(HttpResponseException $exception)
        {
            return $exception->getResponse();
        });
    }

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		//
	}

    protected function initializeRequest(Request $form, HttpRequest $current)
    {
        $files = $current->files->all();

        $files = is_array($files) ? array_filter($files) : $files;

        $form->initialize(
            $current->query->all(), $current->request->all(), $current->attributes->all(),
            $current->cookies->all(), $files, $current->server->all(), $current->getContent()
        );
    }

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}
