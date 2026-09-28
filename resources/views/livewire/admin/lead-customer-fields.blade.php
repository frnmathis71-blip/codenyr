<div class="field full">
    <label for="lead-client-email">Compte client associé (e-mail, facultatif)</label>
    <input id="lead-client-email" type="email" wire:model="client_email" placeholder="E-mail du compte créé par votre client" maxlength="255">
    <small>Le client doit s’inscrire @if(\Laravel\Fortify\Features::enabled(\Laravel\Fortify\Features::emailVerification())) et vérifier son adresse @endif. Renseignez ensuite son e-mail ici pour rattacher ce projet. Ce rattachement ne donne aucun droit administrateur.</small>
    @error('client_email')<span class="error" role="alert">{{ $message }}</span>@enderror
</div>
<div class="field full">
    <label class="inline-check"><input type="checkbox" wire:model="delivered">Site livré — autoriser le client à déposer un avis après acceptation du devis</label>
    @error('delivered')<span class="error" role="alert">{{ $message }}</span>@enderror
</div>
