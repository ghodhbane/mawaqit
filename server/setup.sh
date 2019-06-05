#!/bin/bash

set -e

sudo apt update
sudo apt upgrade
sudo apt dist-upgrade
sudo apt install rsync git acl

# install Docker CE
sudo apt install  apt-transport-https ca-certificates curl gnupg2 software-properties-common
curl -fsSL https://download.docker.com/linux/debian/gpg | sudo apt-key add -
sudo add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/debian  $(lsb_release -cs) stable"
sudo apt update
sudo apt install docker-ce

# install docker-compose
sudo curl -L "https://github.com/docker/compose/releases/download/1.23.2/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chown mawaqit:mawaqit  /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Secure server
sudo echo "PermitRootLogin no" >> /etc/ssh/sshd_config
sudo echo "PasswordAuthentication yes" >> /etc/ssh/sshd_config
sudo echo "AddressFamily inet" >> /etc/ssh/sshd_config

# Customise .bashrc
echo "cd /var/www/mawaqit" >> ~/.bashrc
echo "alias dock=\"docker exec -it --user $(id -u):$(id -g) mawaqit\"" >> ~/.bashrc
