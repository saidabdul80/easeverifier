<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\ResultVerificationController;
use App\Mcp\Tools\Concerns\CallsEaseVerifierApi;
use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[IsDestructive]
#[IsOpenWorld(false)]
class VerifyResult extends AuthenticatedTool
{
    use CallsEaseVerifierApi;

    protected string $name = 'verify_result';

    protected string $title = 'Verify examination result';

    protected string $description = 'Fetch a WAEC, NECO, NBAIS, or NABTEB examination result. This action may consume a checker PIN or token and charge the authenticated customer wallet. Invoke exactly once after explicit user confirmation.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'board' => $schema->string()->enum(['waec', 'neco', 'nbais', 'nabteb'])->description('Examination board.')->required(),
            'txtExamNumber' => $schema->string()->description('WAEC examination number.'),
            'ExamYear' => $schema->string()->description('WAEC examination year.'),
            'ExamType' => $schema->string()->description('WAEC examination type, for example MAY/JUN.'),
            'txtPIN' => $schema->string()->description('WAEC checker PIN.'),
            'txtCardSerialNo' => $schema->string()->description('Optional legacy WAEC card serial number. The current WAEC instant-verification flow only requires the checker PIN.'),
            'exam_year' => $schema->string()->description('NECO examination year.'),
            'exam_type' => $schema->string()->description('NECO or NABTEB examination type.'),
            'reg_no' => $schema->string()->description('NECO registration number.'),
            'token' => $schema->string()->description('NECO result token.'),
            'year' => $schema->string()->description('NBAIS examination year.'),
            'month' => $schema->string()->description('NBAIS examination month or session.'),
            'exam_no' => $schema->string()->description('NBAIS examination number.'),
            'candid' => $schema->string()->description('NABTEB candidate number.'),
            'examtype' => $schema->string()->description('NABTEB examination type code.'),
            'examyear' => $schema->string()->description('NABTEB examination year.'),
            'serial' => $schema->string()->description('NABTEB card serial number.'),
            'pin' => $schema->string()->description('NBAIS or NABTEB result PIN.'),
        ];
    }

    public function handle(Request $request, ResultVerificationController $controller): Response
    {
        $validated = $request->validate([
            'board' => ['required', 'in:waec,neco,nbais,nabteb'],
            'txtExamNumber' => ['nullable', 'string', 'max:50'],
            'ExamYear' => ['nullable', 'string', 'max:10'],
            'ExamType' => ['nullable', 'string', 'max:50'],
            'txtPIN' => ['nullable', 'string', 'max:100'],
            'txtCardSerialNo' => ['nullable', 'string', 'max:100'],
            'exam_year' => ['nullable', 'string', 'max:10'],
            'exam_type' => ['nullable', 'string', 'max:50'],
            'reg_no' => ['nullable', 'string', 'max:50'],
            'token' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'string', 'max:10'],
            'month' => ['nullable', 'string', 'max:50'],
            'exam_no' => ['nullable', 'string', 'max:50'],
            'candid' => ['nullable', 'string', 'max:50'],
            'examtype' => ['nullable', 'string', 'max:50'],
            'examyear' => ['nullable', 'string', 'max:10'],
            'serial' => ['nullable', 'string', 'max:100'],
            'pin' => ['nullable', 'string', 'max:100'],
        ]);

        $board = $validated['board'];
        $payload = array_filter(
            Arr::except($validated, ['board']),
            fn (mixed $value): bool => $value !== null && $value !== '',
        );

        return $this->apiResponse($controller->fetch(
            $this->apiRequest($payload),
            $board,
        ));
    }
}
