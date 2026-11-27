<?php

namespace Klxm\Fluid\Routing;

/**
 * Rolle einer Domain (siehe Domains).
 *
 * @internal
 */
enum DomainRole: string
{
    case Primary = 'primary';
    case Alias = 'alias';
    case Landing = 'landing';
}
