<?php

require_once 'sessao.php';

$_SESSION = [];
session_destroy();

header('Location: ' . BASE_URL . 'pages/login.php');
exit;