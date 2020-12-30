# Getting Started with ESC Platform 
This readme helps set up and run the ESC Platform Infrastructure on your local development machine.

## GitLab Repository Cloning
1) Clone this repository to some location on your machine in the folder `esc-web`: https://github.com/escgametheater/esc-web 
2) Clone the repository `escdockerweb` to a **sibling** folder of the `esc-web` folder: https://github.com/escgametheater/escdockerweb

## Software Requirements Installation
1) Install Docker client for Mac/Windows/Linux: https://docs.docker.com/install/
2) After Docker installation, run Docker.
3) Install PHP + Composer on your local machine (you need this to pull the composer dependencies used by platform PHP): https://getcomposer.org/ 

To install Composer globally, instead of
```
php composer-setup.php
```
do
```
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
```

## Platform Setup
1) Enter into the folder `core/domain/` located in the `esc-web` repository and run `composer install` to download and install all PHP library dependencies required.

2) Enter into the folder you cloned called `escdockerweb`, and run `docker-compose up` to build the containers and start them. In the case of the MYSQL database, this will initialize and bootstrap the SQL DB with default settings using the `schema.sql` file from this repo.)
    
    Tip: If you are running a local MySql DB, you should terminate it so that it doesnt not conflict when you run compose up.
3) Once docker-compose is running all the containers, open `http://localhost:9001` and connect to the Minio (AWS S3 open source server run locally). Use the AWS key/secret credentials from the file `core/domain/conf/default-settings.php` under the AWS config array to log in.

4) Create the following buckets inside Minio if they do not exist: `host-assets`, `host-controller-assets`, `sdk-assets`, game-assets`, `game-controller-assets`, `image-assets`, `game-instance-logs`. These are the buckets the platform will place your user-uploaded content in, respectively of which type they are.    

## Connecting to the ESC platform locally
In order to connect to the ESC platform locally, you will need to create a set of domain/host aliases in your /etc/hosts file that point to your localhost IP. This is due to the fact that the ESC web platform consists of multiple actual "PHP Applications" that run on different sub-domains. Make sure to use the `sudo` command when changing the etc/hosts file.

You will want to choose a "dev hostname identifier" that you use for your local environment. It can be anything you want, though I recommend using some semantic name that makes sense. I use "ccarter" as my dev hostname component in the ETC hosts.
```
127.0.0.1       www.<YOUR_HOSTNAME_HERE>.playesc.com
127.0.0.1       api.<YOUR_HOSTNAME_HERE>.playesc.com
127.0.0.1       play.<YOUR_HOSTNAME_HERE>.playesc.com
127.0.0.1       host.<YOUR_HOSTNAME_HERE>.playesc.com
127.0.0.1       develop.<YOUR_HOSTNAME_HERE>.playesc.com
127.0.0.1       go.<YOUR_HOSTNAME_HERE>.playesc.com
127.0.0.1       images.<YOUR_HOSTNAME_HERE>.playesc.com
```
Replace the `<YOUR_HOSTNAME_HERE>` part with the dev hostname you chose for yourself. Keep in mind you MUST have the .playesc.com suffix for this to work.


# You're ready to get started!
If you followed all the steps above, you should be ready to connect and start working on the PHP platform. Try connecting to `http://www.<YOUR_HOSTNAME_HERE>.playesc.com` in your browser to get started.

You can log in to the platform using the following credentials:

Email: `admin@esc.local`  
Password: `none`