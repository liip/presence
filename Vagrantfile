# -*- mode: ruby -*-
# vi: set ft=ruby :

require 'rbconfig'
WINDOWS = (RbConfig::CONFIG['host_os'] =~ /mswin|mingw|cygwin/) ? true : false
this_dir = File.dirname(__FILE__) + "/"


Vagrant.configure("2") do |config|

  config.vm.box = "precise32"

  # The url from where the 'config.vm.box' box will be fetched if it
  # doesn't already exist on the user's system.
  config.vm.box_url = "http://files.vagrantup.com/precise32.box"

  # NFS only necessary if performance too slow
  config.vm.synced_folder ".", "/vagrant", :ntfs => !WINDOWS

  # whithout this symlinks can't be created on the shared folder
  config.vm.provider :virtualbox do |vb|
    vb.customize ["setextradata", :id, "VBoxInternal2/SharedFoldersEnableSymlinksCreate/v-root", "1"]
  end

  # the hostmaster plugin populates /etc/hosts
  config.vm.hostname = "presence.lo"

  # the ip address where the vm can be accessed from the host
  config.vm.network :private_network, ip: "172.134.86.77"

  # chef solo configuration
  config.vm.provision :chef_solo do |chef|

    chef.cookbooks_path = "./"
    # chef debug level, start vagrant like this to debug:
    # $ CHEF_LOG_LEVEL=debug vagrant <provision or up>
    chef.log_level = ENV['CHEF_LOG'] || "info"

    # chef recipes/roles
    chef.add_recipe("vagrant")
  end
end
