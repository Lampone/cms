<?php

namespace Statamic\Providers;

use Statamic\Facades\User;
use Statamic\Policies;
use Statamic\Auth\UserProvider;
use Statamic\Contracts\Auth\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Contracts\Http\Kernel;
use Statamic\Contracts\Auth\UserGroup;
use Statamic\Contracts\Auth\UserStore;
use Illuminate\Support\ServiceProvider;
use Statamic\Auth\UserRepositoryManager;
use Facades\Statamic\Auth\CorePermissions;
use Statamic\Auth\Passwords\PasswordReset;
use Statamic\Auth\Protect\ProtectorManager;
use Statamic\Contracts\Auth\RoleRepository;
use Statamic\Contracts\Auth\UserRepository;
use Illuminate\Auth\Notifications\ResetPassword;
use Statamic\Contracts\Auth\UserGroupRepository;
use Illuminate\Notifications\Messages\MailMessage;
use Statamic\Auth\Passwords\PasswordBrokerManager;
use Statamic\Auth\Eloquent\UserRepository as EloquentUsers;
use Statamic\Stache\Repositories\UserRepository as StacheUsers;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        \Statamic\Contracts\Structures\Structure::class => Policies\StructurePolicy::class,
        \Statamic\Contracts\Entries\Collection::class => Policies\CollectionPolicy::class,
        \Statamic\Contracts\Entries\Entry::class => Policies\EntryPolicy::class,
        \Statamic\Contracts\Entries\LocalizedEntry::class => Policies\EntryPolicy::class,
        \Statamic\Contracts\Globals\GlobalSet::class => Policies\GlobalSetPolicy::class,
        \Statamic\Contracts\Globals\Variables::class => Policies\GlobalSetVariablesPolicy::class,
        \Statamic\Contracts\Auth\User::class => Policies\UserPolicy::class,
        \Statamic\Contracts\Forms\Form::class => Policies\FormPolicy::class,
        \Statamic\Contracts\Forms\Submission::class => Policies\FormSubmissionPolicy::class,
        \Statamic\Contracts\Assets\Asset::class => Policies\AssetPolicy::class,
        \Statamic\Contracts\Assets\AssetFolder::class => Policies\AssetFolderPolicy::class,
        \Statamic\Contracts\Assets\AssetContainer::class => Policies\AssetContainerPolicy::class,
    ];

    public function register()
    {
        $this->app->singleton(UserRepositoryManager::class, function ($app) {
            return new UserRepositoryManager($app);
        });

        $this->app->singleton(UserRepository::class, function ($app) {
            return $app[UserRepositoryManager::class]->repository();
        });

        $this->app->singleton(RoleRepository::class, function ($app) {
            return $app[UserRepository::class]->roleRepository();
        });

        $this->app->singleton(UserGroupRepository::class, function ($app) {
            return $app[UserRepository::class]->userGroupRepository();
        });

        $this->app->singleton(ProtectorManager::class, function ($app) {
            return new ProtectorManager($app);
        });
    }

    public function boot()
    {
        Auth::provider('statamic', function () {
            return new UserProvider;
        });

        Gate::before(function ($user, $ability) {
            return User::fromUser($user)->isSuper() ? true : null;
        });

        Gate::after(function ($user, $ability) {
            return User::fromUser($user)->hasPermission($ability) === true ? true : null;
        });

        CorePermissions::boot();

        foreach ($this->policies as $key => $policy) {
            Gate::policy($key, $policy);
        }

        $this->app->extend('auth.password', function ($broker, $app) {
            return ($app['auth']->getProvider() instanceof UserProvider)
                ? new PasswordBrokerManager($app)
                : $broker;
        });
    }
}