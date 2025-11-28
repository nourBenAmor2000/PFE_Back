<?php

namespace App\Constants;

/**
 * Centralized role constants for the application
 * These values match what is stored in the database
 */
class Roles
{
    // Admin roles
    public const ADMIN_GLOBAL = 'admin_global';
    
    // Agent roles (stored in database as-is)
    public const AGENT_ADMIN_AGENCE = 'admin_agence';
    public const AGENT_RH = 'rh';
    public const AGENT_PERSONNEL = 'agent';
    
    // Client role
    public const CLIENT = 'client';
    
    /**
     * Get all agent roles
     */
    public static function agentRoles(): array
    {
        return [
            self::AGENT_ADMIN_AGENCE,
            self::AGENT_RH,
            self::AGENT_PERSONNEL,
        ];
    }
    
    /**
     * Get all admin roles (global + agency)
     */
    public static function adminRoles(): array
    {
        return [
            self::ADMIN_GLOBAL,
            self::AGENT_ADMIN_AGENCE,
        ];
    }
    
    /**
     * Check if role is an admin role
     */
    public static function isAdmin(string $role): bool
    {
        return in_array($role, self::adminRoles(), true);
    }
    
    /**
     * Check if role is an agent role
     */
    public static function isAgent(string $role): bool
    {
        return in_array($role, self::agentRoles(), true);
    }
}

