<?php

namespace App\Support;

/**
 * The starting set of categories every new account gets. They are ordinary
 * rows afterwards: the user can rename, reorder, archive or delete them.
 *
 * Icons are emoji so a category is always distinguishable without relying on
 * colour alone.
 */
final class DefaultCategories
{
    /**
     * @return list<array{name: string, group_name: string, icon: string, color: string, is_favorite: bool}>
     */
    public static function all(): array
    {
        return [
            ['name' => 'Trabajo DC', 'group_name' => 'Trabajo', 'icon' => '💼', 'color' => '#60a5fa', 'is_favorite' => true],
            ['name' => 'Trabajo CERI', 'group_name' => 'Trabajo', 'icon' => '🏢', 'color' => '#3b82f6', 'is_favorite' => true],

            ['name' => 'Universidad Clase', 'group_name' => 'Universidad', 'icon' => '🎓', 'color' => '#22d3ee', 'is_favorite' => false],
            ['name' => 'Universidad Tareas', 'group_name' => 'Universidad', 'icon' => '📝', 'color' => '#06b6d4', 'is_favorite' => false],
            ['name' => 'Universidad Estudio', 'group_name' => 'Universidad', 'icon' => '📚', 'color' => '#0891b2', 'is_favorite' => false],

            ['name' => 'Proyecto de Unity', 'group_name' => 'Proyectos y aprendizaje', 'icon' => '🎮', 'color' => '#a855f7', 'is_favorite' => true],
            ['name' => 'Otros Proyectos', 'group_name' => 'Proyectos y aprendizaje', 'icon' => '🛠️', 'color' => '#8b5cf6', 'is_favorite' => false],
            ['name' => 'Aprendizaje', 'group_name' => 'Proyectos y aprendizaje', 'icon' => '🧠', 'color' => '#7c3aed', 'is_favorite' => false],

            ['name' => 'Entrenamiento', 'group_name' => 'Salud y mantenimiento', 'icon' => '🏋️', 'color' => '#34d399', 'is_favorite' => true],
            ['name' => 'Sueño', 'group_name' => 'Salud y mantenimiento', 'icon' => '😴', 'color' => '#64748b', 'is_favorite' => true],
            ['name' => 'Descanso', 'group_name' => 'Salud y mantenimiento', 'icon' => '🛋️', 'color' => '#94a3b8', 'is_favorite' => false],
            ['name' => 'Higiene', 'group_name' => 'Salud y mantenimiento', 'icon' => '🚿', 'color' => '#10b981', 'is_favorite' => false],
            ['name' => 'Comidas', 'group_name' => 'Salud y mantenimiento', 'icon' => '🍽️', 'color' => '#059669', 'is_favorite' => true],

            ['name' => 'Ocio', 'group_name' => 'Tiempo personal', 'icon' => '🎬', 'color' => '#fbbf24', 'is_favorite' => false],
            ['name' => 'Social / Familia', 'group_name' => 'Tiempo personal', 'icon' => '👥', 'color' => '#f59e0b', 'is_favorite' => false],
            ['name' => 'Doom Scrolling', 'group_name' => 'Tiempo personal', 'icon' => '📱', 'color' => '#f87171', 'is_favorite' => true],

            ['name' => 'Transporte', 'group_name' => 'Logística', 'icon' => '🚌', 'color' => '#a3a3a3', 'is_favorite' => false],
            ['name' => 'Tareas del Hogar', 'group_name' => 'Logística', 'icon' => '🧹', 'color' => '#d4d4d8', 'is_favorite' => false],
            ['name' => 'Actividades Inesperadas', 'group_name' => 'Logística', 'icon' => '⚡', 'color' => '#fb923c', 'is_favorite' => false],
            ['name' => 'Otros', 'group_name' => 'Logística', 'icon' => '❓', 'color' => '#71717a', 'is_favorite' => false],
        ];
    }
}
