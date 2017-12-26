# Handler for i-doit

This is an selection of Handler I wrote/improved for public use.

# Installation
Clone or copy the .php to src/handler/

# Overview

## isc_dhcpd
### Use Cases
With this handler you are able to generate the host block for the isc_dhcpd out of the documentation in i-doit.
### Usage
<code>
  ./controller -u <Username> -p <Password> -i <Tenant> -m isc_dhcpd -netaddr <Netaddresse>
</code>
  Every Hostaddresse in i-doit has to have a connection to a network, an hostname, and a connection to a port with an valid MAC.
  Optionally you can document an DNS Server and a search domain.
