const fs=require('fs');function edit(p,a,b){let s=fs.readFileSync(p,'utf8');if(!s.includes(a))throw Error(p+' '+a);fs.writeFileSync(p,s.replace(a,b));}
edit('app/Models/Service.php','/** @property array<int, string>|null $compatible_offers */','/**\n * @property array<int, string>|null $compatible_offers\n * @property array<string, mixed>|null $contract_rules\n */');
edit('app/Livewire/Admin/Billing.php','    public array $ruleEditors = [];','    /** @var array<int, array<string, mixed>> */\n    public array $ruleEditors = [];');
edit('app/Livewire/Admin/Billing.php',"ContractCatalogRules::editor($item['contract_rules'] ?? []), $this->items);","ContractCatalogRules::editor($item['contract_rules']), $this->items);");
edit('app/Livewire/Admin/CommercialDirectory.php','    public array $contractRules = [];','    /** @var array<string, mixed> */\n    public array $contractRules = [];');
edit('app/Services/ContractScopeBuilder.php','    public function build','    /** @return array<string, mixed> */\n    public function build');
edit('app/Services/ContractCatalogRules.php','    public static function editor','    /** @param array<string, mixed> $rules\n     * @return array<string, mixed>\n     */\n    public static function editor');
edit('app/Services/ContractCatalogRules.php','    public static function responsibilityFields','    /** @return list<string> */\n    public static function responsibilityFields');
edit('app/Services/ContractCatalogRules.php','    public static function fromEditor','    /** @param array<string, mixed> $editor\n     * @param array<string, mixed> $original\n     * @return array<string, mixed>\n     */\n    public static function fromEditor');
edit('app/Services/ContractCatalogRules.php',"preg_split('/\\R/u', $editor[$kind] ?? '')", "(preg_split('/\\R/u', $editor[$kind] ?? '') ?: [])");
edit('app/Services/ContractCatalogRules.php','    public static function validate','    /** @param array<string, mixed> $rules\n     * @return array<string, mixed>\n     */\n    public static function validate');
edit('app/Services/ContractCatalogRules.php','    public static function forService','    /** @return array<string, mixed> */\n    public static function forService');
edit('app/Services/ContractCatalogRules.php','    public static function freeze','    /** @param array<int, array<string, mixed>> $items\n     * @return array<string, mixed>\n     */\n    public static function freeze');
