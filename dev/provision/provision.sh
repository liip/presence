#!/bin/bash

echo "Setting Locale Settings"
export LANGUAGE="en_US.UTF-8"
echo 'LANGUAGE="en_US.UTF-8"' >> /etc/default/locale
echo 'LC_ALL="en_US.UTF-8"' >> /etc/default/locale

echo "Installing vim/mc and set MC as default editor"
apt-get install -y vim vim-doc vim-scripts mc >> /tmp/vagrant_log 2>&1
update-alternatives --set editor /usr/bin/mcedit >> /tmp/vagrant_log 2>&1

echo "Installing Apache and PHP"
apt-get install -y php-apc php5 php5-cli php5-curl php5-gd php5-intl php5-mcrypt php5-mysql php-pear php5-xdebug php5-sqlite php5-dev >> /tmp/vagrant_log 2>&1

echo "Configuring Apache and PHP"
a2dissite 000-default >> /tmp/vagrant_log 2>&1
cp /vagrant/dev/provision/presence.conf /etc/apache2/sites-available/presence.conf
a2ensite presence >> /tmp/vagrant_log 2>&1
a2enmod rewrite >> /tmp/vagrant_log 2>&1

cp /vagrant/dev/provision/php/php5.ini /etc/php5/apache2/php.ini
cp /vagrant/dev/provision/php/php5.ini /etc/php5/cli/php.ini
sed -i 's/\(APACHE_RUN_USER=\)www-data/\1vagrant/g' /etc/apache2/envvars
chown vagrant:www-data /var/lock/apache2
service apache2 restart >> /tmp/vagrant_log 2>&1

# install composer
echo "Installing composer"
if [ ! -f "/usr/local/bin/composer" ];
then
    php -r "readfile('https://getcomposer.org/installer');" | php  >> /tmp/vagrant_log 2>&1
    mv composer.phar /usr/local/bin/composer >> /tmp/vagrant_log 2>&1
fi

echo "done"

echo "Installing composer dependencies"
cd /vagrant
composer install --no-interaction >> /tmp/vagrant_log 2>&1

echo "Change SSH login dir"
echo "cd /vagrant" >> /home/vagrant/.bashrc
echo "done"
