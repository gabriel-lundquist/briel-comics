# MySQL installation

Port number: 3307

## Users
root: signin level 2

## How to set up an SQL instance
0. Install MySQL using [their Windows MSI](https://dev.mysql.com/downloads/mysql/)

1. [Initialize a data directory using `mysqld --initialize`](https://dev.mysql.com/doc/refman/8.0/en/data-directory-initialization.html) 

2. [Start the server using `mysqld` and an options file to specify its port and data directory](https://dev.mysql.com/doc/refman/8.0/en/multiple-windows-command-line-servers.html)

3. On Windows, [set up the server as a Windows service so it automatically starts on boot](https://dev.mysql.com/doc/refman/8.0/en/windows-start-service.html)

## How to run an SQL instance on a non-default data directory
Theoretically you should just be able to pass in an options file using the `--defaults-file` option but I keep having problems with that, so

1. Once the server is running, connect using `msql --port=#` where \# is the port number specified in your options file.