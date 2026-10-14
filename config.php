<?php
return [
    'dsn' => getenv('POKER_DB_DSN') ?: 'pgsql:host=127.0.0.1;port=5432;dbname=dbp',
    'user' => getenv('POKER_DB_USER') ?: 'postgres',
    'password' => getenv('POKER_DB_PASSWORD') ?: 'postgres111',
];
