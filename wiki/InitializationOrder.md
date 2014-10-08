##PW Core hooks and call hierarchy

###Hook 1: plugins_loaded

1. PWrapper::init
2. PWrapper::init_hooks
3. PW_Component::init
4. PW_Component::init_hooks
5. PWrapper::init_plugins
    1. Plugin::init
    2. Plugin::init_hooks

###Hook 2: setup_theme

1. PWrapper::init_theme
    1. Theme::init
    2. Theme::init_hooks
    3. Theme::init_theme
    4. Plugin::init_theme

###Hook 3: init

1. PWrapper::init_components
    1. Plugin::*_init
    2. Theme::*_init

##PW Components hooks and call hierarchy

###PW Ajax

1. *plugins_loaded*: PW_Ajax::init_hooks
2. *init*: PW_Ajax::ajax_init
3. *admin_init*: PW_Ajax::security_callback
4. *admin_footer*, *wp_footer*: PW_Ajax::print_callback
5. *wp_ajax_{action}*: PW_Ajax::wrapper_callback
    - PW_Module::{action}_ajax

