#PWrapper - WordPress as Application Framework

PWrapper is a framework addon to make your WordPress development easier with a set of conventions.
It is based on classes and magic methods to let you create WP Applications that are readable and powerful.

We rely on Pods Framework for types platform, but it´s not a dependency.

##Design Overview

PW let you create WP Apps in OOP fashion.

Use the `PW_Module` class extension to create your application classes. Every `PW_Module` class is read by PWrapper and has its **magic methods** run.

    class App_Pool extends PW_Module
    {

    }

PW Magic Methods are *static function* in your class that have magic sufixes.

    class App_Pool extends PW_Module
    {
        static function app_pool_shortcode()
        {
            echo 'My own (damm simple) pool shortcode';
        }
    }

Your class files must live in a specific folder designed to make your apps structured.

##Features

- **Ajax** - make sense of your logic using class methods for your ajax functions

- **Widgets** - easiest way to create widgets ever

- **Shortcodes** - shortcodes never has been so easy and powerful

- **Capabilities** - define capabilities in your own class object and create roles

- **Notices** - session based notices that works

- **Types** - make sense of your business logic using class based types

- **Template** - use h2o template language to level your WP applications

##Magic methods

The magic of PW is its *function sufix system* which let us simplify the WP development process plus greatly improve system archtecture and readability.

An example magic method is `shortcode`:

    class App_Pool extends PW_Module
    {
        static function app_pool_shortcode()
        {
            echo 'My own pool shortcode';
        }
    }

PW registers (by magic) your shortcode with the name `[app_poll]`. Just use it.

##Folder hierarchy

PW makes use of a specific folder hirarchy. It includes every class of your application.

On your theme or plugin, the following folder structure should exist

    - theme-v2
        - pwrapper
            - modules
                - class.php
            - templates
                - view.html

Every php file in `modules` folder gets included in your WP runtime automatically. When using templates, each module `template` folder gets into lookup hierarchy.

##Modules

####Core

The first magic methods you should be aware of are the Core Module methods:

- **init**: to init your object soon as it gets included
- **init_hooks**: to add actions and filters your class depends on

    class App_Pool extends PW_Module
    {
        static function init()
        {
            // initialize the class dependencies
        }
        static function init_hooks()
        {
            // initialize the class hooks
            add_action( 'admin_enqueue_scripts', array( 'App_Pool', 'register_scripts' ) );
        }
    }

####Ajax


WP Ajax functionality is easily integrated within PW - JavaScript and your classes lives transparently. You can create public or private (admin only) ajax methods.

The syntax is `method_ajax` for admin functions and `method_ajax_public` for front-end functions.

An example usage would be:

    class App_Pool extends PW_Module
    {
        static function vote_ajax_public($pool, $option)
        {
            pods('pool')->add([
                'pool'    => $pool,
                'options' => $option,
            ]);
            return true;
        }
    }

PW creates all JavaScript needed to call your method transparently:

        // call your class method directly,
        // with the last parameter as an AJAX function callback
        App_Pool.vote(pool, option, function(response) {
            if (response)
                alert('Vote succeed');
            else
                alert('Error occurred');
        });

It returns your php method response or echo to the request. Simple like this. Security validation included.


####Shortcodes

Shortcodes is created by naming your method with the `_shortcode` magic sufix. It also has a `POST` callback, an innovation that allows your shortcodes to interact with http requests - like in forms.

    class App_Pool extends PW_Module
    {
        static function app_pool_shortcode($atts)
        {
            $pool = pods('pool', $atts['id']);
            $context = ['pool' => $pool->row];
            echo PW_Template::render('app/pool.html', $context);
        }
    }

This simple snippet register your shortcode and allow you to use it in your posts:

    [app_pool id=12]

####Templates

Despites WordPress has its own template system, we find useful to have a template engine at hand to use within our applications. So we ship a simple yet powerful engine, the **php-h2o**.

This is how a template looks like

    <h3>{{ pool.title }}</h3>
    <select>
    {% for opt in options %}
        <option value="{{ opt.id }}">{{ opt.name }}</select>
    {% endfor %}
    </ul>
    <input type="submit" value="Send" />

This template was saved in `pwrapper/templates/app/pool.html`, inside our theme.

Then to render it use the `PW_Template` class which introduces the `render` method to deal with.

    $context = [
        'pool' => pods('pool', 1)->row,
        'options' => pods('options')->find(['where'=>'pool = 1'])->rows,
    ];
    echo PW_Template::render('app/pool.html', $context);

Please check [h2o wiki](https://github.com/speedmax/h2o-php/wiki) for further information on the templating language.

####Types

WordPress Types are a fundamental piece in the WP as Application Framework concept. It is powerful, but not enought for most business apps. For that reason [pods-framework](http://pods.io/) is been around solving cases that requires complex database designs.

*PW Types* aims to improve and structure your objects so you can keep the **business logic** in a readable fashion.

It ships magic methods for `save` and `delete`. We are transparently encapsulating [pods hooks](http://pods.io/docs/code/filter-reference/pods_api_pre_save_pod_item_podname/), so you can fully rely on the framework engine.

    class App_Pool extends PW_Module
    {
        static function pool_save_before($pieces, $is_new_item)
        {
            if ("Is this plugin useful?" = $pieces['fields']['title']['value'])
                pods_die('You know it little guy!');

            return $pieces;
        }
    }

Here `pool` is our type name as defined in pods. The `_save_before` sufix is found by PW which register the hook.

The magic sufixes available are `save_before`, `save_after`, `delete_before`, `delete_after`.

####Widgets

Widgets has always been painful to create and manage. Not anymore! PW puts the whole complexity of Widgets in a single magic sufix.

    class App_Pool extends PW_Module
    {
        static function pool_widget($args, $instance)
        {
            $context = [
                'pool' => pods('pool', 1)->row,
                'options' => pods('options')->find(['where'=>'pool = 1'])->rows,
            ];
            echo PW_Template::render('app/pool.html', $context);
        }
    }

The widget is registred and displayed in WP Admin by the name `Pool`. It is also possible to use the admin form and update methods with `_widget_form` and `_widget_update` magic sufixes.

####Capabilities

Define capabilities in the context of your object, creating a method which return the definitions in this format:

    array(
        'rolename' => [
            'capabality_1',
            ['capabality_remove', false],
        ];
    )

A working example would be:

    class App_Pool extends PW_Module
    {
        static function capabilities()
        {
            return array(
                'editor' => [
                    'manage_pool',
                    'delete_pool',
                ];
            );
        }
    }

PW inserts the capabilities in the role of choice, so you just check for them in your class methods with WP core function `current_user_can( 'manage_pool' )`.

####Admin Menu

This magic method is called to create WP Admin Menus.
Your method should return an array of menu configurations.
 
Here is a menu array example format:

    return array(
         'my-menu' => [ // the slug of the top menu entry
               'menu'        => 'Menu Title',
               'title'       => 'My Action',
               'capability'  => 'manage_options',
               'callback'    => ['App_Pool', 'menu_callback'], // function which renders the menu
               'ui'          => [], // pods_ui definition
               'form'        => [], //
               'icon'        => '',
               'position'    => null,
               'parent'      => null,
               'remove'      => false,
               'submenu'         => [] // submenus defined as above
         ]
    )
  
Most of this options are optional. Usually we'll use just `callback` or `ui`

An example `ui` definition would be as follows:

    return array(
         'app-pool' => [
               'capability'  => 'manage_poll',
               'submenu'     => [
                    'pools' => [
                        'ui' => array(
                            'pod' => 'pool',
                            'actions_custom' => array(
                                'increase' => []
                            ),
                        ),
                    ]
               ]
         ]
    )

The options are the same of [pods_ui](http://pods.io/docs/code/pods-ui/).

####Notices

PW manages session based notices, allowing runtime to send messages to user in any subsequent vizualization.

    PW_Notice::add('Please remember to keep track of the plugin in github');

It prints the messages in the `admin_notices` or `loop_start` hooks. The notice is displayed the next time a configured hook is triggered.
