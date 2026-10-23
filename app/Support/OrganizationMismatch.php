<?php

namespace App\Support;

use DomainException;

/** Попытка создать запись без организации или сослаться на запись чужой организации */
class OrganizationMismatch extends DomainException {}
