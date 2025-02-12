<h1>Bebbo CMS - Drupal content management system</h1>

[Bebbo website](https://bebbo.app/)

## Table of Contents
- Introduction  
- Installation  
- Pre-requisites  
- Configuration  
- Maintainers  
- Community  

## Introduction
Parent Buddy CMS application is a headless implementation of Drupal 8 CMS where the content is added through the web interface and serves as REST APIs for a mobile app. This application assists editors in adding different types of content under various content types and taxonomies configured in Drupal CMS. Go through the [onboarding document](./ONBOARDING.md) before continuing with the Installation guidelines below.  

For more information on setup and getting started, check out our [guidelines for contributors](./CONTRIBUTING.md).   

## Installation  

### Pre-requisites
Before installing the Bebbo CMS application, ensure that you have the following software installed on your development machine:  
- **Install PHP**: Ensure php is installed correctly and set up on your machine. You can follow the installation guide [here](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx).  
- **Install composer**: [Composer Installation Guide](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx).  
- **Optional - global composer installation**: [Global Composer Setup](https://getcomposer.org/doc/00-intro.md#globally).  
  If skipping, replace composer with php composer.phar for your setup.  
- **Install Drush**: `composer global require drush/drush`  

### Configuration
After installing all the prerequisites, follow the steps below to set up the Bebbo CMS:  
For Windows users, before proceeding to the next step, run the following command:  
```
git config --global core.longpaths true
```
Clone the repository from GitHub using the following command:  
```
git clone https://github.com/UNICEFECAR/parenting-app-bebbo-CMS
```
Download the database from the Acquia server and import it locally. If you don’t have access to Acquia, you can download the dump database here.  

Modify the database details in the **settings.php** file, which is located at:  
```
docroot/sites/default/settings.php
```
Launch the application in your browser to verify everything is set up correctly.  

## Maintainers
The Bebbo CMS is actively maintained by UNICEF's Regional Office for Europe and Central Asia in collaboration with various partners. It is part of the larger Bebbo project, a digital parenting platform aimed at providing parents and caregivers with essential early childhood development resources.  

For ongoing maintenance, please reach out to the following maintainers:  
- [Evrim Sahin](https://github.com/evrimm)
- [Akhror Abduvaliev](https://github.com/Akhror)
- [Saurabh Agarwal](https://github.com/saurabhEDU)
- [Muhammed Osman](https://github.com/Akhror)

## Community
Unicef Bebbo has a friendly and lively open-source community. Our communication happens primarily primarily in our [Github Discussion](https://github.com/UNICEFECAR/parenting-app-bebbo-CMS/discussions) and we welcome all interested contributors to join the conversation.