# TimeClock

[![Yii2](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat)](https://www.yiiframework.com/)

#### This fork provides necessary functionality for an external [timeclock terminal](https://github.com/niclasku/rpi-timeclock-terminal) and some other modifications.

Simple work time clocking service built on [Yii 2 framework](https://www.yiiframework.com).

![screen](https://bizley.github.io/timeclock/tc-dark.png)

## Installation

1. Install TimeClock using Composer:
  
    `composer create-project --prefer-dist bizley/timeclock timeclock`
    
2. Prepare virtual host pointing to `/public` directory.
3. Prepare configuration for DB of your choice. Place it in `/src/config/db.php`.
4. Modify the `/src/config/web.php` file to change:

    - `timeZone` (default `UTC`)
    - `language` (default `en-US`; `pl` and `de` translations are provided in `/src/messages/` folder)
    - `components > mailer` configuration to actually send emails (needed for password reset)
    - `components > formatter` configuration of date and time formats
    - `params > company` (default `Company Name`; displayed in footer and other layout places)
    - `params > email` (default `email@company.com`; used as the email sender address for emails)
    - `params > allowedDomains` (default `['@company.com']`; array with email domains allowed for registration)
    - `params > employeeSessionEdit` (default `true`; allows employees to edit own sessions)
    - `params > employeeSessionDelete` (default `true`; allows employees to delete own sessions)
    - `params > employeeOffTimeEdit` (default `true`; allows employees to edit own off-times)
    - `params > employeeOffTimeDelete` (default `true`; allows employees to delete own off-times)
    - `params > employeeOffTimeApprovedDelete` (default `true`; allows employees to delete own approved off-times)
    - `params > adminSessionAdd` (default `false`; allows admins to add sessions)
    - `params > adminSessionEdit` (default `false`; allows admins to edit every session)
    - `params > adminSessionDelete` (default `false`; allows admins to delete every session)
    - `params > adminOffTimeAdd` (default `false`; allows admins to add off-time)
    - `params > adminOffTimeEdit` (default `false`; allows admins to edit every off-time)
    - `params > adminOffTimeDelete` (default `false`; allows admins to delete every off-time)
    - `params > showAllVacations` (default `false`; shows off-times of the whole year first)
    - `params > approvableOffTime` (default `[Off::TYPE_SICK, Off::TYPE_VACATION]`; defines which off-time types need an approval)
    - `params > weekendDays` (default `[7]`; defines which days of the week are actually weekend for employees)
    - `params > uploadPath` (default `dirname(__DIR__) . '/../uploads/'`; path to photo upload folder)

5. Change `/public/index.php` file to set `YII_DEBUG` mode to `false` and `YII_ENV` environment to `prod`.
6. Apply migrations by running in console `php yii migrate`.
7. Start webserver and register first account.
8. If you want to make an account to be admin run in console `php yii admin/set ID` where `ID` is DB identifier of account 
   to be set (usually first one is `1`).
9. If you want to use an external timeclock terminal you can get a special API token via `php yii terminal/add`.
   
## Ground rules

- Registering account requires its email address to be in one of the provided domains. If you want to change this behavior 
  you must prepare your own code. Current implementation is at `/src/models/RegisterForm.php` and `/src/views/site/register.php`.
- Session can be started at any time but it must be ended not overlapping any other ended session.
- There can be many sessions in one day.
- Session can not be longer than midnight.
- Not ended sessions not count for work hours.
- Off-time must not overlap any other off-time period.
- Holidays are automatically fetched from `https://www.kalendarzswiat.pl` which is Polish holiday list. If you want to 
  use something different you must prepare your own code for this. Current implementation is at `/src/models/Holiday.php`.

## Features

- account registration
- password reset
- profile update
- themes
- signing in with login or PIN
- session time with note
- off-time with note
- session and off-time history
- calendar
- holidays
- admin section
- REST API
- Bootstrap 4 layout

## New in 2.3.0

- vacations requests
- projects
- sessions time CSV download for admins
- deactivating accounts for admins

## Upgrading from 2.2.1 to 2.3.0

1. Update all the project files to match the repository.
2. Apply migrations by running in console `php yii migrate`.

## General help

Read [TimeClock Wiki](https://github.com/bizley/timeclock/wiki) first.

For anything related to Yii go to the [Yii 2 Guide](https://www.yiiframework.com/doc/guide/2.0/en).  
I really don't want to point obvious links with solutions from there.

## Usage of this project

You can use this project in whatever way you like as long as you mention where did you get it from.

## Screenshots

![screen2](https://bizley.github.io/timeclock/tc-light.png)

![screen3](https://bizley.github.io/timeclock/tc-sunlight.png)

![screen4](https://bizley.github.io/timeclock/tc-history.png)

![screen5](https://bizley.github.io/timeclock/tc-calendar.png)
