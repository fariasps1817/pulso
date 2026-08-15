<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mensagens de validacao
|--------------------------------------------------------------------------
|
| Tom de voz do Pulso: direto, sem jargao e sem culpar quem preencheu. A
| mensagem diz o que fazer, nao o que a pessoa errou. Quem usa o sistema e
| recepcionista, gerente e dono — nao analista de TI.
|
*/

return [

    'accepted' => 'É preciso aceitar :attribute.',
    'accepted_if' => 'É preciso aceitar :attribute quando :other for :value.',
    'active_url' => 'Informe um endereço de internet válido em :attribute.',
    'after' => 'A data em :attribute precisa ser posterior a :date.',
    'after_or_equal' => 'A data em :attribute precisa ser :date ou posterior.',
    'alpha' => 'Use somente letras em :attribute.',
    'alpha_dash' => 'Use somente letras, números, hífen e sublinhado em :attribute.',
    'alpha_num' => 'Use somente letras e números em :attribute.',
    'any_of' => 'O valor informado em :attribute não é válido.',
    'array' => 'O campo :attribute precisa ser uma lista.',
    'array_keys' => 'O campo :attribute só aceita as chaves: :values.',
    'ascii' => 'Use somente letras, números e símbolos simples em :attribute.',
    'base64' => 'O campo :attribute precisa conter um texto em Base64 válido.',
    'before' => 'A data em :attribute precisa ser anterior a :date.',
    'before_or_equal' => 'A data em :attribute precisa ser :date ou anterior.',
    'between' => [
        'array' => 'Selecione de :min a :max itens em :attribute.',
        'file' => 'O arquivo em :attribute precisa ter de :min a :max kilobytes.',
        'numeric' => 'O valor em :attribute precisa estar entre :min e :max.',
        'string' => 'O campo :attribute precisa ter de :min a :max caracteres.',
    ],
    'boolean' => 'O campo :attribute só aceita sim ou não.',
    'can' => 'O campo :attribute contém um valor não permitido.',
    'confirmed' => 'A confirmação de :attribute não confere.',
    'contains' => 'Falta um valor obrigatório em :attribute.',
    'current_password' => 'A senha atual está incorreta.',
    'date' => 'Informe uma data válida em :attribute.',
    'date_equals' => 'A data em :attribute precisa ser :date.',
    'date_format' => 'A data em :attribute precisa estar no formato :format.',
    'decimal' => 'O campo :attribute precisa ter :decimal casas decimais.',
    'declined' => 'É preciso recusar :attribute.',
    'declined_if' => 'É preciso recusar :attribute quando :other for :value.',
    'different' => 'Os campos :attribute e :other precisam ser diferentes.',
    'digits' => 'O campo :attribute precisa ter :digits dígitos.',
    'digits_between' => 'O campo :attribute precisa ter de :min a :max dígitos.',
    'dimensions' => 'A imagem em :attribute está fora das dimensões aceitas.',
    'distinct' => 'O campo :attribute está repetido.',
    'doesnt_contain' => 'O campo :attribute não pode conter: :values.',
    'doesnt_end_with' => 'O campo :attribute não pode terminar com: :values.',
    'doesnt_start_with' => 'O campo :attribute não pode começar com: :values.',
    'email' => 'Informe um e-mail válido em :attribute.',
    'encoding' => 'O campo :attribute precisa estar codificado em :encoding.',
    'ends_with' => 'O campo :attribute precisa terminar com: :values.',
    'enum' => 'A opção escolhida em :attribute não é válida.',
    'exists' => 'A opção escolhida em :attribute não existe.',
    'extensions' => 'O arquivo em :attribute precisa ser do tipo: :values.',
    'file' => 'O campo :attribute precisa conter um arquivo.',
    'filled' => 'Preencha o campo :attribute.',
    'gt' => [
        'array' => 'Selecione mais de :value itens em :attribute.',
        'file' => 'O arquivo em :attribute precisa ter mais de :value kilobytes.',
        'numeric' => 'O valor em :attribute precisa ser maior que :value.',
        'string' => 'O campo :attribute precisa ter mais de :value caracteres.',
    ],
    'gte' => [
        'array' => 'Selecione :value itens ou mais em :attribute.',
        'file' => 'O arquivo em :attribute precisa ter :value kilobytes ou mais.',
        'numeric' => 'O valor em :attribute precisa ser maior ou igual a :value.',
        'string' => 'O campo :attribute precisa ter :value caracteres ou mais.',
    ],
    'hex_color' => 'Informe uma cor hexadecimal válida em :attribute.',
    'image' => 'O campo :attribute precisa conter uma imagem.',
    'in' => 'A opção escolhida em :attribute não é válida.',
    'in_array' => 'O campo :attribute precisa existir em :other.',
    'in_array_keys' => 'O campo :attribute precisa conter ao menos uma das chaves: :values.',
    'integer' => 'O campo :attribute precisa ser um número inteiro.',
    'ip' => 'Informe um endereço IP válido em :attribute.',
    'ipv4' => 'Informe um endereço IPv4 válido em :attribute.',
    'ipv6' => 'Informe um endereço IPv6 válido em :attribute.',
    'json' => 'O campo :attribute precisa conter um JSON válido.',
    'list' => 'O campo :attribute precisa ser uma lista.',
    'lowercase' => 'Escreva :attribute em letras minúsculas.',
    'lt' => [
        'array' => 'Selecione menos de :value itens em :attribute.',
        'file' => 'O arquivo em :attribute precisa ter menos de :value kilobytes.',
        'numeric' => 'O valor em :attribute precisa ser menor que :value.',
        'string' => 'O campo :attribute precisa ter menos de :value caracteres.',
    ],
    'lte' => [
        'array' => 'Selecione no máximo :value itens em :attribute.',
        'file' => 'O arquivo em :attribute precisa ter :value kilobytes ou menos.',
        'numeric' => 'O valor em :attribute precisa ser menor ou igual a :value.',
        'string' => 'O campo :attribute precisa ter :value caracteres ou menos.',
    ],
    'mac_address' => 'Informe um endereço MAC válido em :attribute.',
    'max' => [
        'array' => 'Selecione no máximo :max itens em :attribute.',
        'file' => 'O arquivo em :attribute pode ter no máximo :max kilobytes.',
        'numeric' => 'O valor em :attribute pode ser no máximo :max.',
        'string' => 'O campo :attribute pode ter no máximo :max caracteres.',
    ],
    'max_digits' => 'O campo :attribute pode ter no máximo :max dígitos.',
    'mimes' => 'O arquivo em :attribute precisa ser do tipo: :values.',
    'mimetypes' => 'O arquivo em :attribute precisa ser do tipo: :values.',
    'min' => [
        'array' => 'Selecione ao menos :min itens em :attribute.',
        'file' => 'O arquivo em :attribute precisa ter ao menos :min kilobytes.',
        'numeric' => 'O valor em :attribute precisa ser ao menos :min.',
        'string' => 'O campo :attribute precisa ter ao menos :min caracteres.',
    ],
    'min_digits' => 'O campo :attribute precisa ter ao menos :min dígitos.',
    'missing' => 'O campo :attribute não pode ser enviado.',
    'missing_if' => 'O campo :attribute não pode ser enviado quando :other for :value.',
    'missing_unless' => 'O campo :attribute não pode ser enviado, a menos que :other seja :value.',
    'missing_with' => 'O campo :attribute não pode ser enviado junto com :values.',
    'missing_with_all' => 'O campo :attribute não pode ser enviado junto com :values.',
    'multiple_of' => 'O valor em :attribute precisa ser múltiplo de :value.',
    'not_in' => 'A opção escolhida em :attribute não é válida.',
    'not_regex' => 'O formato de :attribute não é válido.',
    'numeric' => 'O campo :attribute precisa ser um número.',
    'password' => [
        'letters' => 'A senha precisa ter ao menos uma letra.',
        'mixed' => 'A senha precisa ter ao menos uma letra maiúscula e uma minúscula.',
        'numbers' => 'A senha precisa ter ao menos um número.',
        'symbols' => 'A senha precisa ter ao menos um símbolo.',
        'uncompromised' => 'Essa senha já apareceu em vazamentos de dados. Escolha outra.',
    ],
    'present' => 'O campo :attribute precisa ser enviado.',
    'present_if' => 'O campo :attribute precisa ser enviado quando :other for :value.',
    'present_unless' => 'O campo :attribute precisa ser enviado, a menos que :other seja :value.',
    'present_with' => 'O campo :attribute precisa ser enviado junto com :values.',
    'present_with_all' => 'O campo :attribute precisa ser enviado junto com :values.',
    'prohibited' => 'O campo :attribute não é permitido.',
    'prohibited_if' => 'O campo :attribute não é permitido quando :other for :value.',
    'prohibited_if_accepted' => 'O campo :attribute não é permitido quando :other for aceito.',
    'prohibited_if_declined' => 'O campo :attribute não é permitido quando :other for recusado.',
    'prohibited_unless' => 'O campo :attribute não é permitido, a menos que :other esteja em :values.',
    'prohibits' => 'O campo :attribute impede o envio de :other.',
    'regex' => 'O formato de :attribute não é válido.',
    'required' => 'Preencha o campo :attribute.',
    'required_array_keys' => 'O campo :attribute precisa conter: :values.',
    'required_if' => 'Preencha :attribute quando :other for :value.',
    'required_if_accepted' => 'Preencha :attribute quando :other for aceito.',
    'required_if_declined' => 'Preencha :attribute quando :other for recusado.',
    'required_unless' => 'Preencha :attribute, a menos que :other esteja em :values.',
    'required_with' => 'Preencha :attribute quando :values for informado.',
    'required_with_all' => 'Preencha :attribute quando :values forem informados.',
    'required_without' => 'Preencha :attribute quando :values não for informado.',
    'required_without_all' => 'Preencha :attribute quando nenhum de :values for informado.',
    'same' => 'Os campos :attribute e :other precisam ser iguais.',
    'size' => [
        'array' => 'Selecione exatamente :size itens em :attribute.',
        'file' => 'O arquivo em :attribute precisa ter :size kilobytes.',
        'numeric' => 'O valor em :attribute precisa ser :size.',
        'string' => 'O campo :attribute precisa ter :size caracteres.',
    ],
    'starts_with' => 'O campo :attribute precisa começar com: :values.',
    'string' => 'O campo :attribute precisa ser um texto.',
    'timezone' => 'Informe um fuso horário válido em :attribute.',
    'unique' => 'Esse :attribute já está em uso.',
    'uploaded' => 'Não foi possível enviar o arquivo em :attribute. Tente de novo.',
    'uppercase' => 'Escreva :attribute em letras maiúsculas.',
    'url' => 'Informe um endereço de internet válido em :attribute.',
    'ulid' => 'O campo :attribute precisa conter um ULID válido.',
    'uuid' => 'O campo :attribute precisa conter um UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | Mensagens especificas por campo
    |--------------------------------------------------------------------------
    |
    | Use "campo.regra" quando a mensagem generica nao servir. Vale a pena
    | sempre que o texto puder dizer a saida em vez de so apontar o erro.
    |
    */

    'custom' => [
        'password' => [
            'min' => 'A senha precisa ter ao menos :min caracteres.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nomes dos campos
    |--------------------------------------------------------------------------
    |
    | Nomeie pelo que a pessoa reconhece: "mensalidade", "matricula",
    | "catraca" — nunca "registro" ou "endpoint".
    |
    */

    'attributes' => [
        'bairro' => 'bairro',
        'celular' => 'celular',
        'cep' => 'CEP',
        'cidade' => 'cidade',
        'cpf' => 'CPF',
        'current_password' => 'senha atual',
        'data_nascimento' => 'data de nascimento',
        'data_vencimento' => 'data de vencimento',
        'email' => 'e-mail',
        'name' => 'nome',
        'nome' => 'nome',
        'observacoes' => 'observações',
        'password' => 'senha',
        'password_confirmation' => 'confirmação da senha',
        'telefone' => 'telefone',
        'uf' => 'estado',
        'valor' => 'valor',
    ],

];
