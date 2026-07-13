<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleDataAccessSetting extends Model
{
    protected $fillable = [
        'role_id',
        'team_scope',
        'participant_scope',
    ];

    public const TEAM_SCOPES = [
        'none' => 'Keine Mitarbeiter',
        'own_projects' => 'Eigene Projekte',
        'department' => 'Komplette Abteilung',
        'all' => 'Alle Mitarbeiter',
    ];

    public const PARTICIPANT_SCOPES = [
        'none' => 'Keine Teilnehmer',
        'current_project_same_location' => 'Aktuelles Projekt + eigene Standorte',
        'own_projects' => 'Eigene Projekte',
        'own_locations' => 'Eigene Standorte',
        'department' => 'Komplette Abteilung',
        'all' => 'Alle Teilnehmer',
    ];

    private const TEAM_RANKS = [
        'none' => 0,
        'own_projects' => 10,
        'department' => 20,
        'all' => 30,
    ];

    private const PARTICIPANT_RANKS = [
        'none' => 0,
        'current_project_same_location' => 10,
        'own_projects' => 20,
        'own_locations' => 30,
        'department' => 40,
        'all' => 50,
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public static function valuesForRole(Role $role): array
    {
        $setting = self::where('role_id', $role->id)->first();
        $defaults = self::defaultScopesForRoleName($role->name);

        return [
            'team_scope' => $setting?->team_scope ?? $defaults['team'],
            'participant_scope' => $setting?->participant_scope ?? $defaults['participant'],
        ];
    }

    public static function defaultScopesForRoleName(?string $roleName): array
    {
        return match ($roleName) {
            'Administrator', 'Developer', 'Sekretariat', 'Geschäftsführer', 'Geschäftsleitung' => [
                'team' => 'all',
                'participant' => 'all',
            ],
            'Abteilungsleitung', 'Assistenz der Abt.-Leitung' => [
                'team' => 'department',
                'participant' => 'department',
            ],
            'Sozialpädagoge' => [
                'team' => 'own_projects',
                'participant' => 'own_locations',
            ],
            'Projektleitung', 'Pädagogische Leitung', 'Ausbilder' => [
                'team' => 'own_projects',
                'participant' => 'own_projects',
            ],
            'Anleiter' => [
                'team' => 'own_projects',
                'participant' => 'current_project_same_location',
            ],
            default => [
                'team' => 'none',
                'participant' => 'none',
            ],
        };
    }

    public static function scopeForUser(User $user, string $area): string
    {
        $column = $area === 'team' ? 'team_scope' : 'participant_scope';
        $ranks = $area === 'team' ? self::TEAM_RANKS : self::PARTICIPANT_RANKS;
        $bestScope = 'none';
        $bestRank = 0;

        foreach ($user->roles()->get(['roles.id', 'roles.name']) as $role) {
            $setting = self::where('role_id', $role->id)->first();
            $defaults = self::defaultScopesForRoleName($role->name);
            $scope = $setting?->{$column} ?? $defaults[$area];
            $rank = $ranks[$scope] ?? 0;

            if ($rank > $bestRank) {
                $bestScope = $scope;
                $bestRank = $rank;
            }
        }

        return $bestScope;
    }
}
