<?php

namespace App\Http\Controllers;

use App\Services\Messenger\ChatDistributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChatDistributionController extends Controller
{
    public function __construct(
        private ChatDistributionService $distribution,
    ) {}

    public function index(Request $request): Response
    {
        $company = $request->user()->company()->firstOrFail();
        $mode = $this->distribution->modeForCompany($company);
        $agents = $this->distribution->eligibleAgents((int) $company->id);

        return Inertia::render('ChatDistribution/Index', [
            'mode' => $mode,
            'modes' => [
                [
                    'value' => ChatDistributionService::MODE_EVEN,
                    'label' => 'Равномерно',
                    'description' => 'Новые чаты по очереди назначаются сотрудникам, у которых есть доступ к Месенджеру.',
                ],
                [
                    'value' => ChatDistributionService::MODE_FIRST_RESPONDER,
                    'label' => 'Кто отвечает первым',
                    'description' => 'Новый чат видят все сотрудники с доступом к Месенджеру. Чат закрепляется за тем, кто первым ответит.',
                ],
            ],
            'eligibleAgents' => $agents->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'position_name' => $user->position?->name,
            ])->values(),
            'pageTitle' => 'Распределение чата',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $request->user()->company()->firstOrFail();

        $validated = $request->validate([
            'mode' => [
                'required',
                'string',
                Rule::in([
                    ChatDistributionService::MODE_EVEN,
                    ChatDistributionService::MODE_FIRST_RESPONDER,
                ]),
            ],
        ]);

        $this->distribution->updateMode($company, $validated['mode']);

        return back()->with('success', __('Настройка распределения сохранена.'));
    }
}
