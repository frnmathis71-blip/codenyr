const fs=require('fs');function edit(p,a,b){let s=fs.readFileSync(p,'utf8');if(!s.includes(a))throw Error(p+' '+a);fs.writeFileSync(p,s.replace(a,b));}
edit('app/Models/Document.php','/** @property array<string, mixed>|null $snapshot */',`/**
 * @property array<string, mixed>|null $snapshot
 * @property array<string, mixed>|null $contract_data
 * @property \\Carbon\\CarbonInterface $document_date
 * @property \\Carbon\\CarbonInterface|null $finalized_at
 */`);
edit('app/Models/ClientProject.php',' * @property CarbonInterface|null $starts_on',' * @property array<string, mixed>|null $contract_data\n * @property CarbonInterface|null $starts_on');
edit('app/Models/DocumentTemplate.php','class DocumentTemplate extends Model','/** @property array<string, mixed>|null $parameters */\nclass DocumentTemplate extends Model');
edit('app/Models/Client.php','class Client extends Model',`/**
 * @property string|null $audience
 * @property string|null $company
 * @property string|null $country
 * @property string|null $vat_number
 * @property string|null $signatory
 * @property string|null $signatory_role
 */
class Client extends Model`);
edit('app/Livewire/Admin/CommercialDocuments.php','    public array $contract = [];','    /** @var array<string, mixed> */\n    public array $contract = [];');
edit('app/Livewire/Admin/CommercialDocuments.php',"$project = ClientProject::find($this->form['client_project_id'] ?? null);","$project = ClientProject::whereKey($this->form['client_project_id'] ?? null)->first();");
edit('app/Services/LegalDocumentRules.php','    public static function sections()', '    /** @return array<string, array<string, string>> */\n    public static function sections()');
edit('app/Services/LegalDocumentRules.php','    public static function defaults()', '    /** @return array<string, string> */\n    public static function defaults()');
edit('app/Services/LegalDocumentRules.php','    public static function choices(', '    /** @return list<string> */\n    public static function choices(');
edit('app/Services/ProjectLegalDocuments.php','    public function prepare(', '    /** @return array<string, mixed> */\n    public function prepare(');
edit('app/Services/ProjectLegalDocuments.php',"DocumentTemplate::find($input['terms_template_id'] ?? null)","DocumentTemplate::whereKey($input['terms_template_id'] ?? null)->first()");
edit('app/Services/ProjectLegalDocuments.php',"$document->document_date?->format", "$document->document_date->format");
edit('app/Services/ProjectLegalDocuments.php',"foreach (preg_split('/\\R/u', (string) $fields['exclusions']) as $exclusion)","foreach (preg_split('/\\R/u', (string) $fields['exclusions']) ?: [] as $exclusion)");
edit('app/Services/CommercialPdf.php',"$document->finalized_by = auth()->id();", "$document->finalized_by = auth()->user()?->id;");
edit('app/Services/CommercialPdf.php',"                    $mime = (new \\finfo", "                    if (! is_string($logo) || $logo === '') {\n                        throw new RuntimeException('Le logo du document ne peut pas être lu.');\n                    }\n                    $mime = (new \\finfo");
