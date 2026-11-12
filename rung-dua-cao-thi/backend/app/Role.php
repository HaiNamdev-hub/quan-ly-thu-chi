<?php

namespace App;

enum Role: string
{
    case Admin = 'ADMIN';
    case Manager = 'MANAGER';
    case Staff = 'STAFF';
}
