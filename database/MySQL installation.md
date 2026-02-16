# MySQL installation

Port number: 3307

## Users
root: signin level 2

## How to set up an SQL instance
0. Install MySQL using [their Windows MSI](https://dev.mysql.com/downloads/mysql/)

1. [Initialize a data directory using `mysqld --initialize`](https://dev.mysql.com/doc/refman/8.0/en/data-directory-initialization.html) 

2. [Start the server using `mysqld` and an options file to specify its port and data directory](https://dev.mysql.com/doc/refman/8.0/en/multiple-windows-command-line-servers.html)

3. On Windows, [set up the server as a Windows service so it automatically starts on boot](https://dev.mysql.com/doc/refman/8.0/en/windows-start-service.html)

