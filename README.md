# PoGoScanner
## About

A simple scanner based on website platforms (like PokeHunt, PokeHunter, etc.)

## Requirements
1. PHP 5.3+
2. MySQL 5.1+
3. Composer (https://getcomposer.org/download/)

## Installation

1. git clone this repo
2. make a <code>composer install</code> in the root of the repo
3. play SQL files <code>database.sql</code> and <code>data.sql</code> to create database structure and some datas
4. Edit <code>config/database.inc.php</code> to define access to database

## Launch scanner
Just launch scanner.php in PHP CLI :

<code>php scanner.php</code>

## View results
Create a VirtualHost with the repository root path as DocumentRoot and index.php as default file.