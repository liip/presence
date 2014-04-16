execute "apt-get update"

package "python-software-properties"

execute "add php 5.4 repository" do
  not_if "grep ondrej /etc/apt/sources.list.d/*"
  command "add-apt-repository ppa:ondrej/php5 && apt-get update"
end

# install the software we need
%w(
curl
tmux
vim
git
libapache2-mod-php5
php5-cli
php5-curl
php5-intl
php5-dev
php-apc
php-pear
).each { | pkg | package pkg }

template "/home/vagrant/.bash_aliases" do
  user "vagrant"
  mode "0644"
  source ".bash_aliases.erb"
end

template "/etc/apache2/sites-enabled/vhost.conf" do
  user "root"
  mode "0644"
  source "vhost.conf.erb"
  notifies :reload, "service[apache2]"
end

execute "a2enmod rewrite"

service "apache2" do
  supports :restart => true, :reload => true, :status => true
  action [ :enable, :start ]
end

execute "disable default site" do
  only_if "a2query -s 000-default.conf"
  command "a2dissite 000-default.conf"
  notifies :reload, "service[apache2]"
end

bash "install phpunit " do
  not_if "which phpunit"
  user "root"
  code <<-EOH
      pear channel-discover pear.phpunit.de
      pear channel-discover pear.symfony.com
      pear install --alldeps phpunit/PHPUnit
      EOH
end

bash "retrieve composer" do
  user "vagrant"
  cwd "/vagrant"
  code <<-EOH
  set -e

  # create bin folder
  mkdir -p bin

  # check if composer is installed
  if [ ! -f bin/composer.phar ]
  then
    curl -s https://getcomposer.org/installer | php -- --install-dir=bin
  else
    php bin/composer.phar selfupdate
  fi
  EOH
end

bash "run composer" do
  user "vagrant"
  cwd "/vagrant"
  code <<-EOH
  set -e
  export COMPOSER_HOME=/home/vagrant
  bin/composer.phar install --dev
  EOH
end
