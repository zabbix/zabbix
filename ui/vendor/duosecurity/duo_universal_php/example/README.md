# Duo Universal PHP SDK Demo

A simple PHP web application that serves a logon page integrated with Duo 2FA.

## Setup
Change to the "example" directory
```
cd example
```

Install the demo requirements:
```
composer update
```

Then, create a `Web SDK` application in the Duo Admin Panel. See https://duo.com/docs/protecting-applications for more details.

## Using the App

1. Copy the Client ID, Client Secret, and API Hostname values for your `Web SDK` application into the `duo.conf` file.
1. Start the app.
    ```
    php -d session.save_path=/tmp -S localhost:8080
    ```
1. Navigate to http://localhost:8080.
1. Log in with the user you would like to enroll in Duo or with an already enrolled user (any password will work).

## (Optional) Run the demo using docker

A dockerfile is included to easily run the demo app with a known working PHP configuration.

1. Copy the Client ID, Client Secret, and API Hostname values for your `Web SDK` application into the `duo.conf` file.
1. Build the docker image: `docker build -t duo_php_example .`
1. Run the docker container `docker run -p 8080:8080 duo_php_example`
1. Navigate to http://localhost:8080.
1. Log in with the user you would like to enroll in Duo or with an already enrolled user (any password will work).
