-- Base dédiée aux tests PHPUnit (exécuté uniquement à la première initialisation du volume).
CREATE DATABASE marketplace_testing OWNER marketplace;
\connect marketplace_testing
CREATE EXTENSION IF NOT EXISTS postgis;
