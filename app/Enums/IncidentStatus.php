<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case PENDING = 'pending';
    case INVESTIGATING = 'investigating';
    case RESOLVED = 'resolved';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Aguardando análise/atribuição',
            self::INVESTIGATING => 'Em acompanhamento',
            self::RESOLVED => 'Concluída',
            self::ARCHIVED => 'Arquivada',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => '#f59e0b',
            self::INVESTIGATING => '#115cb9',
            self::RESOLVED => '#22c55e',
            self::ARCHIVED => '#737780',
        };
    }
}
