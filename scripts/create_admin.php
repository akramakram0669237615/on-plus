<?php
require __DIR__.'/../src/Database.php';
[$script,$username,$email,$password]=array_pad($argv,4,null);
if(!$username||!$email||!$password){fwrite(STDERR,"Usage: php scripts/create_admin.php USER EMAIL PASSWORD\n");exit(1);}
$pdo=App\Database::pdo();
$pdo->prepare('INSERT INTO admins(username,email,password_hash) VALUES(?,?,?)')->execute([$username,$email,password_hash($password,PASSWORD_DEFAULT)]);
echo "Admin created\n";
