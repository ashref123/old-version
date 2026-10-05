<?php

namespace App\Transformers\Agent;

use App\Transformers\Transformer;
use App\Models\Payment\AgentBankInfo;

class AgentBankInfoTransformer extends Transformer
{
    /**
     * Resources that can be included if requested.
     *
     * @var array
     */
    protected array $availableIncludes = [

    ];

    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform(AgentBankInfo $agentBankInfo)
    {
        // dd($driverBankInfo->bankInfo);

        $params = [
            'id' => $agentBankInfo->id,
            'method_id' => $agentBankInfo->method_id,
            'field_id' => $agentBankInfo->field_id,
            'agent_id' => $agentBankInfo->driver_id,
            'value' => $agentBankInfo->value,
        ];

        return $params;
    }
}
