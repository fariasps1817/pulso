<?php

declare(strict_types=1);

namespace App\Livewire\Administracao;

use App\Enums\SituacaoAcademia;
use App\Models\Academia;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * As academias que usam o Pulso.
 *
 * O QUE ESTA TELA NÃO MOSTRA, E POR QUÊ
 *
 * Nenhum nome de aluno, nenhuma mensalidade, nenhum acesso. As políticas de
 * Row Level Security não abrem exceção para o super administrador, e é isso
 * que faz uma conta da equipe do Pulso comprometida não virar uma porta para
 * o dado de todos os clientes.
 *
 * O que aparece é o plano de controle: situação, unidades, usuários — e o
 * TOTAL de alunos, que é um número mantido pela própria academia, não uma
 * consulta aos alunos dela.
 */
#[Layout('layouts.administracao', ['secao' => 'academias'])]
#[Title('Academias')]
final class Academias extends Component
{
    #[Url(as: 'busca', except: '')]
    public string $termo = '';

    #[Url(as: 'situacao', except: 'todas')]
    public string $filtro = 'todas';

    public function render(): View
    {
        $academias = $this->consultar();

        return view('livewire.administracao.academias', [
            'academias' => $academias,
            'situacoes' => SituacaoAcademia::cases(),
            'totais' => [
                'academias' => $academias->count(),
                'alunos' => $academias->sum('total_alunos_ativos'),
                'com_filial' => $academias->filter(fn (Academia $a): bool => $a->unidades_count > 1)->count(),
            ],
        ]);
    }

    /** @return Collection<int, Academia> */
    private function consultar()
    {
        return Academia::query()
            // `unidades` e `users` são plano de controle: ficam fora do
            // isolamento, e por isso podem ser contados daqui.
            ->withCount([
                'unidades' => fn (Builder $q) => $q->where('ativa', true),
                'usuarios' => fn (Builder $q) => $q->where('ativo', true),
            ])
            ->when($this->termo !== '', fn (Builder $q) => $q->where(
                fn (Builder $busca) => $busca
                    ->where('nome', 'ilike', '%'.trim($this->termo).'%')
                    ->orWhere('cnpj', 'ilike', '%'.preg_replace('/\D/', '', $this->termo).'%'),
            ))
            ->when($this->filtro !== 'todas', fn (Builder $q) => $q->where('situacao', $this->filtro))
            ->orderBy('nome')
            ->get();
    }
}
