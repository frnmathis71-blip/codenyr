from pathlib import Path
from html import escape

from reportlab.pdfgen import canvas
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak, KeepTogether, Flowable
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_LEFT
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.lib.utils import ImageReader
from pypdf import PdfReader
import pdfplumber

ROOT = Path(__file__).resolve().parents[2]
OUTPUT = ROOT / 'output/pdf/Guide-utilisation-Codenyr-gestion-commerciale.pdf'
OUTPUT.parent.mkdir(parents=True, exist_ok=True)
LOGO = ROOT / 'public/images/codenyr.png'
pdfmetrics.registerFont(TTFont('Guide', 'C:/Windows/Fonts/arial.ttf'))
pdfmetrics.registerFont(TTFont('GuideBold', 'C:/Windows/Fonts/arialbd.ttf'))
pdfmetrics.registerFont(TTFont('GuideItalic', 'C:/Windows/Fonts/ariali.ttf'))
pdfmetrics.registerFontFamily('Guide', normal='Guide', bold='GuideBold', italic='GuideItalic', boldItalic='GuideBold')

NAVY = colors.HexColor('#142337')
BLUE = colors.HexColor('#2563EB')
MUTED = colors.HexColor('#58667A')
PALE = colors.HexColor('#EFF5FF')
BORDER = colors.HexColor('#DCE4EF')
WHITE = colors.white
PAGE_W, PAGE_H = 595.28, 841.89
WIDTH = PAGE_W - 88

STYLES = {
    'body': ParagraphStyle('Body', fontName='Guide', fontSize=9.7, leading=13.5, textColor=NAVY, spaceAfter=6),
    'small': ParagraphStyle('Small', fontName='Guide', fontSize=8.7, leading=12.6, textColor=MUTED, spaceAfter=6),
    'h1': ParagraphStyle('H1', fontName='GuideBold', fontSize=25, leading=29, textColor=NAVY, spaceAfter=14),
    'h2': ParagraphStyle('H2', fontName='GuideBold', fontSize=13, leading=17, textColor=NAVY, spaceBefore=10, spaceAfter=6),
    'tag': ParagraphStyle('Tag', fontName='GuideBold', fontSize=8.5, leading=12, textColor=BLUE, spaceAfter=6),
    'cell': ParagraphStyle('Cell', fontName='Guide', fontSize=9, leading=11.8, textColor=NAVY),
    'headcell': ParagraphStyle('HeadCell', fontName='GuideBold', fontSize=9, leading=11.8, textColor=WHITE),
    'white': ParagraphStyle('White', fontName='Guide', fontSize=11, leading=17, textColor=WHITE),
}
story = []

def p(text, style='body'):
    return Paragraph(text, STYLES[style])

def add(text, style='body'):
    story.append(p(text, style))

def h(text):
    add(text, 'h2')

def step(number, title, text):
    story.append(KeepTogether([p(f'{number:02d}  {title}', 'h2'), p(text)]))

def box(title, text):
    t = Table([[p(title, 'tag')], [p(text)]], colWidths=[WIDTH-28])
    t.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,-1), PALE), ('BOX', (0,0), (-1,-1), .6, BORDER),
        ('LEFTPADDING',(0,0),(-1,-1),14), ('RIGHTPADDING',(0,0),(-1,-1),14),
        ('TOPPADDING',(0,0),(-1,0),12), ('BOTTOMPADDING',(0,0),(-1,0),0),
        ('BOTTOMPADDING',(0,-1),(-1,-1),10),
    ]))
    story.append(Spacer(1,8))
    story.append(t)
    story.append(Spacer(1,8))

def table(headers, rows, widths):
    data = [[p(escape(cell), 'headcell') for cell in headers]]
    data += [[p(str(cell), 'cell') for cell in row] for row in rows]
    t = Table(data, colWidths=widths, repeatRows=1, hAlign='LEFT')
    t.setStyle(TableStyle([
        ('BACKGROUND',(0,0),(-1,0),NAVY), ('VALIGN',(0,0),(-1,-1),'TOP'),
        ('ROWBACKGROUNDS',(0,1),(-1,-1),[WHITE, colors.HexColor('#F6F8FB')]),
        ('LINEBELOW',(0,0),(-1,-1),.4,BORDER), ('LEFTPADDING',(0,0),(-1,-1),10),
        ('RIGHTPADDING',(0,0),(-1,-1),10), ('TOPPADDING',(0,0),(-1,-1),7),
        ('BOTTOMPADDING',(0,0),(-1,-1),7),
    ]))
    story.append(t)
    story.append(Spacer(1,8))

def start(chapter, title, introduction):
    if story:
        story.append(PageBreak())
    add(chapter.upper(), 'tag')
    add(title, 'h1')
    add(introduction)

class CoverBanner(Flowable):
    def __init__(self):
        Flowable.__init__(self)
        self.width = WIDTH
        self.height = 225

    def draw(self):
        c = self.canv
        c.setFillColor(NAVY)
        c.roundRect(0,0,self.width,self.height,12,fill=1,stroke=0)
        c.drawImage(ImageReader(str(LOGO)), 25, 147, width=230,height=230/3,mask='auto')
        c.setFillColor(colors.HexColor('#9EBEFF'))
        c.setFont('GuideBold',9)
        c.drawString(28,130,'GUIDE PRATIQUE / ADMINISTRATION')
        c.setFillColor(WHITE)
        c.setFont('GuideBold',27)
        c.drawString(28,89,'Votre gestion commerciale')
        c.setFont('Guide',13)
        c.drawString(28,61,'Du premier projet au dernier paiement')
        c.setFont('Guide',9)
        c.drawString(28,28,'Version du 1er octobre 2026')


# 1
story.append(Spacer(1,5))
story.append(CoverBanner())
story.append(Spacer(1,14))
add('Un dossier pour tout retrouver', 'h1')
add('Ce guide explique les fonctionnalités récemment ajoutées à Codenyr, telles qu’elles fonctionnent dans l’administration actuelle. Il vous accompagne pour créer un projet, préparer les documents et suivre les règlements.')
box('LE PARCOURS À RETENIR', '<b>Client → Projet → Devis → Documents et signatures → Factures → Paiements → Archivage</b><br/>La fiche projet rassemble les actions et les pièces du dossier.')
h('Retrouver rapidement une explication')
table(['Sujet', 'Page'], [
    ['Les menus et les mots à connaître', '2'], ['Les réglages et le catalogue', '3'],
    ['Créer un client et son projet', '4'], ['Préparer et accepter un devis', '5'],
    ['Facturer et enregistrer les paiements', '6'], ['Documents, CGV et signatures', '7'],
    ['Maintenance, notes et suivi du dossier', '8'], ['Un exemple complet, étape par étape', '9'],
    ['Questions courantes et routine quotidienne', '10'],
], [WIDTH-55,55])

# 2
start('01 / Repères', 'Comprendre les nouveaux menus', 'Connectez-vous avec votre compte administrateur. Les modules commerciaux sont accessibles depuis le menu de l’administration.')
table(['Menu', 'À quoi il sert'], [
    ['<b>Projets</b>', 'Créer et ouvrir les dossiers commerciaux ; filtrer les projets actifs, terminés ou archivés.'],
    ['<b>Clients</b>', 'Conserver les coordonnées de facturation et les informations du contact.'],
    ['<b>Devis</b>', 'Préparer une proposition, télécharger son PDF et enregistrer la réponse du client.'],
    ['<b>Factures</b>', 'Préparer puis émettre les factures classiques, d’acompte, intermédiaires ou de solde.'],
    ['<b>Documents</b>', 'Importer, générer, retrouver et télécharger les pièces ; accéder aux modèles de textes.'],
    ['<b>Catalogue</b>', 'Gérer les prestations proposées dans les cases à cocher du devis.'],
    ['<b>Recherche</b>', 'Retrouver un client, un projet, un numéro ou un document.'],
    ['<b>Paramètres commerciaux</b>', 'Configurer l’identité Codenyr, la TVA, les mentions, les délais et les alertes.'],
], [147,WIDTH-147])
h('Client, prospect et projet : trois rôles différents')
add('Le <b>client</b> porte les coordonnées commerciales. Le <b>prospect</b> est la demande reçue depuis le site. Le <b>projet</b> est le dossier d’un travail précis : création d’un site, refonte, maintenance ou développement spécifique. Un même client peut avoir plusieurs projets.')
add('Les <b>Réalisations</b> restent le portfolio public. Un dossier créé dans Projets n’est pas publié automatiquement sur le site. Les anciennes archives de prospects restent dans le menu Archives ; les archives commerciales se retrouvent dans le filtre Archivés de Projets.')
box('CLIENT COMMERCIAL ET COMPTE DE CONNEXION', 'Créer une fiche client ne crée pas un compte de connexion. L’espace client et le droit de déposer un avis continuent de dépendre de l’association explicite du compte dans Prospects, du devis accepté et de la livraison confirmée dans ce parcours existant.')

# 3
start('02 / Première configuration', 'Préparer vos réglages et tarifs', 'Avant le premier document destiné à un client, renseignez les informations qui seront reprises dans les futurs devis, factures et contrats.')
step(1, 'Compléter les Paramètres commerciaux', 'Indiquez le nom commercial, la raison sociale, l’adresse, le téléphone, l’e-mail et l’identifiant de l’entreprise. Vous pouvez ajouter le logo des futurs documents : PNG ou JPEG, jusqu’à 2 Mo. Sans nouveau fichier, le logo Codenyr existant est utilisé.')
step(2, 'Configurer la TVA et les documents', 'Choisissez si la TVA est activée, son taux par défaut et la mention fiscale à afficher. Renseignez les conditions des devis, les mentions des factures et le pied de page. Les textes sont ceux que vous saisissez dans l’administration.')
step(3, 'Choisir les valeurs par défaut', 'Réglez les préfixes de numérotation, la validité des devis, le délai de paiement et le pourcentage d’acompte. Les réglages initiaux prévoient 30 jours de validité, 30 jours de paiement et 40 % d’acompte ; ils sont modifiables. Les seuils de relance et de livraison proche sont également configurables.')
h('Administrer le Catalogue')
add('Les quatre offres de création et les trois formules de maintenance existantes sont reprises dans le catalogue. Pour ces prestations liées aux tarifs publics, modifier le prix met aussi à jour la source utilisée dans Tarifs. Les prestations supplémentaires ont leur propre tarif.')
table(['Champ', 'Utilisation'], [
    ['Catégorie et nom', 'Organiser les cases à cocher : Création, Fonctionnalités, SEO, Maintenance…'],
    ['Description et unité', 'Décrire ce qui est inclus ; choisir forfait, heure ou autre unité.'],
    ['Prix et fréquence', 'Saisir le prix HT ; choisir ponctuel, mensuel, trimestriel ou annuel.'],
    ['Active et ordre', 'Afficher ou masquer une prestation ; régler sa position dans sa catégorie.'],
], [145,WIDTH-145])
box('LES ANCIENS DOCUMENTS GARDENT LEURS DONNÉES', 'Un devis enregistré conserve les prix, descriptions et coordonnées copiés lors de sa préparation. Changer le catalogue ou les réglages ne réécrit pas les documents déjà enregistrés. Les modèles de CGV et contrats suivent le même principe.')

# 4
start('03 / Créer le dossier', 'Créer un client et un projet', 'Le projet devient le point d’entrée pour tout gérer. Vous pouvez partir d’un nouveau dossier ou d’une demande déjà reçue.')
step(1, 'Ouvrir Projets puis + Nouveau projet', 'Sélectionnez un client existant dans la liste. Si vous choisissez Créer un nouveau client, renseignez son entreprise ou son nom et son e-mail ; ajoutez le contact, le téléphone et l’adresse si vous les connaissez.')
step(2, 'Décrire le travail à réaliser', 'Saisissez le nom du projet, son type et sa description. Ajoutez les dates de début et de livraison prévues, le domaine, l’hébergeur et les notes internes. Les dates peuvent rester vides si elles ne sont pas encore fixées.')
step(3, 'Renseigner l’URL si elle existe', 'Le champ URL du site est facultatif. Vous pouvez saisir <b>www.exemple.fr</b> ou <b>exemple.fr</b> : Codenyr ajoute https://. Une adresse complète avec http:// ou https:// est également acceptée. Une valeur incorrecte produit maintenant un message explicite en français.')
step(4, 'Cliquer sur Créer le dossier', 'Vous arrivez sur la fiche du projet. Les coordonnées du client, les montants, les documents et le suivi sont réunis ici. Utilisez Modifier le projet pour mettre à jour les informations ou le statut.')
h('Créer depuis un prospect existant')
add('Dans <b>Prospects</b>, ouvrez le détail de la demande puis cliquez sur <b>Créer un projet commercial depuis ce prospect</b>. Les coordonnées et la description sont préremplies. Vous pouvez choisir une fiche client existante ; le prospect original et ses avis sont conservés.')
h('Naviguer dans la fiche projet')
add('<b>Résumé</b> présente les indicateurs et la checklist. Les onglets <b>Devis, Factures, Paiements, Documents, Contrats, Maintenance, Fichiers, Notes et Historique</b> donnent accès aux différents éléments. Le bouton <b>+ Ajouter au dossier</b> propose les actions courantes.')
box('LE STATUT DU PROJET SE GÈRE SÉPARÉMENT', 'Vous choisissez le statut opérationnel : devis à préparer, en attente d’acompte, en développement, en validation, terminé, maintenance… Accepter un devis ou saisir un paiement ne change pas automatiquement ce statut. Mettez-le à jour selon l’avancement réel.')

# 5
start('04 / Devis', 'Construire et suivre une proposition', 'Depuis la fiche projet, cliquez sur Créer un devis. Le projet et son client sont présélectionnés. Vous pouvez aussi commencer depuis le menu Devis.')
step(1, 'Choisir les prestations', 'Cochez les prestations dans le catalogue : elles deviennent des lignes du devis. Utilisez <b>+ Ligne personnalisée</b> pour un besoin spécifique. Chaque ligne peut avoir son nom, sa description, sa quantité, son unité, son prix, sa remise et sa TVA.')
step(2, 'Vérifier l’aperçu des montants', 'Les totaux se recalculent pendant la saisie. La remise globale en euros s’applique au paiement initial ; la remise globale en pourcentage s’applique à chaque fréquence. Une remise fixe ne peut pas dépasser son montant de référence.')
step(3, 'Séparer options, récurrences et acompte', 'Une option facultative ne compte dans le total que si elle est retenue. Les lignes mensuelles, trimestrielles et annuelles sont affichées séparément du prix initial. L’acompte peut être absent, fixe ou en pourcentage ; il porte sur le montant initial.')
step(4, 'Enregistrer puis générer le PDF', 'Cliquez sur <b>Enregistrer le brouillon</b>. Le devis reçoit un numéro distinct des factures, par exemple DEV-2026-0001. Le bouton <b>Générer le PDF enregistré</b> produit la version enregistrée : sauvegardez les changements avant de demander un nouveau PDF.')
step(5, 'Enregistrer l’envoi et la réponse', 'Après vérification, utilisez <b>Marquer envoyé</b> : le contenu se fige et le PDF est conservé. Vous transmettez le PDF vous-même au client. Dans Suivi du devis, choisissez ensuite Accepté, Refusé, Expiré ou Annulé et mettez le statut à jour.')
box('COMMENT MODIFIER UN DEVIS DÉJÀ ENVOYÉ ?', 'Utilisez <b>Dupliquer</b>. Codenyr crée un nouveau brouillon avec un nouveau numéro et conserve l’original. Après acceptation, le devis reste figé. L’acceptation est enregistrée manuellement par l’administrateur ; le logiciel n’envoie pas l’e-mail et ne recueille pas une signature électronique.')
add('<b>À retenir :</b> un devis accepté représente un accord commercial. Le montant encaissé dépend des paiements réellement enregistrés sur les factures.', 'small')

# 6
start('05 / Factures et règlements', 'Facturer puis enregistrer l’argent reçu', 'Depuis un devis accepté, utilisez Créer la facture depuis le devis. Sur la fiche projet, les actions Facturer l’acompte et Facturer le solde sont aussi disponibles.')
table(['Type de facture', 'Quand l’utiliser'], [
    ['Classique', 'Facturer le montant initial en une fois, avant toute autre facture émise pour ce devis.'],
    ['Acompte', 'Facturer l’acompte prévu dans le devis.'],
    ['Intermédiaire', 'Facturer une partie supplémentaire : indiquez le montant TTC souhaité.'],
    ['Solde', 'Facturer ce qui reste après les factures déjà émises sur le même devis.'],
], [132,WIDTH-132])
add('Une facture créée depuis un devis reprend ses informations et tient compte des montants déjà émis. Son contenu financier est calculé depuis le devis et conservé dans le brouillon. Si un brouillon existe déjà pour ce devis, il est réouvert. Vous pouvez également créer une facture manuelle depuis le projet ou le menu Factures.')
step(1, 'Contrôler le brouillon', 'Vérifiez le projet, les dates, l’échéance, les mentions et le montant. Les périodes de maintenance sont facturées à part avec une ligne ponctuelle décrivant la période concernée.')
step(2, 'Émettre la facture', 'Cliquez sur <b>Émettre la facture</b> et confirmez. Le numéro FAC-2026-0001, par exemple, est attribué à l’émission. Le contenu et le numéro sont ensuite conservés. Téléchargez le PDF pour le transmettre au client.')
step(3, 'Saisir chaque paiement reçu', 'Dans l’onglet <b>Paiements</b> du projet, sélectionnez la facture émise. Indiquez le montant, la date réelle, le moyen de paiement, puis une référence ou un commentaire si nécessaire. Cliquez sur <b>Enregistrer le paiement</b>. Plusieurs paiements partiels sont possibles.')
table(['Indicateur', 'Ce qu’il mesure'], [
    ['Facturé', 'La somme des factures émises, y compris celles archivées.'],
    ['Payé', 'La somme des paiements que vous avez enregistrés.'],
    ['Reste dû', 'La différence entre les factures émises et leurs règlements.'],
    ['Reste à facturer', 'Le montant du devis accepté qui n’a pas encore été facturé.'],
], [132,WIDTH-132])
add('Un paiement doit être positif, daté d’aujourd’hui ou d’une date antérieure, et ne pas dépasser le reste dû. Les règlements sont saisis manuellement : aucune connexion bancaire ou encaissement en ligne n’est déclenché par ce formulaire.', 'small')

# 7
start('06 / Documents et signatures', 'Conserver toutes les pièces du dossier', 'Depuis le projet, utilisez Ajouter / importer un document, ou ouvrez Documents. La bibliothèque permet de filtrer par client, projet, type, date, année et statut.')
h('Importer un fichier existant')
add('Choisissez <b>Importer un fichier</b>, donnez un nom au document, sélectionnez son type et son projet, puis sa date. Ajoutez une description ou des notes. Sélectionnez le fichier et enregistrez. Formats acceptés : <b>PDF, DOCX, XLSX, PNG et JPEG</b>, jusqu’à <b>20 Mo</b>.')
add('Le client est déduit du projet sélectionné. Sans projet, choisissez une fiche client. Pour un logo, des images ou des textes de travail, utilisez le type <b>Fichier de travail</b> : ces éléments se retrouvent dans l’onglet Fichiers du projet.')
h('Créer des CGV ou un contrat depuis un modèle')
add('Ouvrez <b>Documents → Modèles de textes</b>. Ajoutez un modèle, choisissez CGV ou le type de contrat, puis rédigez son contenu et enregistrez. Pour une pièce concrète, choisissez <b>Créer depuis un texte / modèle</b>, son projet et le modèle. Sélectionnez un devis accepté associé pour reprendre ses montants, prestations et conditions. Adaptez le texte avant de l’enregistrer, puis générez le PDF.')
table(['Variable du modèle', 'Information insérée'], [
    ['{{client}} / {{project}}', 'Nom du client / nom du projet.'],
    ['{{seller}}', 'Raison sociale ou nom commercial Codenyr configuré.'],
    ['{{amount}} / {{delay}}', 'Total initial TTC / délai du devis associé.'],
    ['{{services}} / {{payment}}', 'Prestations retenues / conditions du devis associé.'],
], [190,WIDTH-190])
h('Rattacher une version signée')
add('Sur la ligne du document original, cliquez sur <b>Importer la version signée</b>. Choisissez le fichier revenu du client et enregistrez. Le document signé est lié à l’original et conservé séparément. La bibliothèque indique Version signée ou Signature reçue.')
box('APERÇU, TÉLÉCHARGEMENT ET VERSIONS', 'Les PDF et images peuvent être visualisés. Les imports proposent <b>Télécharger l’original</b> ; les pièces générées proposent <b>Télécharger le PDF</b>. Les versions PDF précédentes restent accessibles depuis l’aperçu lorsqu’il en existe plusieurs. Modifier un modèle général ne change pas les documents déjà créés.')

# 8
start('07 / Suivi du dossier', 'Maintenance, notes et avancement', 'La fiche projet sert aussi au suivi quotidien, au-delà des documents financiers.')
h('Créer une maintenance')
add('Dans l’onglet <b>Maintenance</b>, préparez la formule : nom, montant, fréquence mensuelle/trimestrielle/annuelle, date de début et durée en mois. Renseignez le renouvellement, les prestations incluses, le délai d’intervention, les modalités de résiliation et les notes. Cliquez sur <b>Ajouter la maintenance</b>.')
add('Sur la fiche de maintenance, vous pouvez <b>Activer, Suspendre, Résilier ou Archiver</b>. Le bouton <b>Générer le contrat</b> ouvre un document prérempli avec les informations de la maintenance : complétez le texte, enregistrez, générez le PDF et importez ensuite la version signée si nécessaire.')
box('FACTURER LA MAINTENANCE', 'La maintenance reste distincte du prix initial du projet. Son activation ne crée pas automatiquement de factures périodiques. Pour une période à facturer, créez une facture manuelle et une ligne ponctuelle, par exemple « Maintenance - octobre 2026 ».')
h('Utiliser Notes et Historique')
add('Dans <b>Notes</b>, saisissez les informations internes utiles, puis cliquez sur Ajouter la note. Vous pouvez épingler une note pour la retrouver en premier. L’auteur et la date sont conservés. <b>Historique</b> présente les événements significatifs : création, documents, envoi du devis, paiements, changements de statut et archivage.')
h('Lire la checklist et les alertes')
add('Le <b>Dossier administratif</b> vérifie la présence des pièces et étapes : devis, acceptation, CGV et contrat signés, factures et paiements. Les éléments d’acompte apparaissent si un devis accepté prévoit un acompte ; les éléments de maintenance apparaissent lorsqu’une maintenance est présente. Le pourcentage décrit le dossier administratif, pas l’avancement du développement.')
add('Les alertes peuvent signaler un devis absent, un devis envoyé sans réponse, une signature manquante, un acompte à recevoir, une facture échue ou une livraison proche. Elles aident à prioriser les actions sans bloquer la navigation. Le dashboard général reprend les indicateurs et les actions requises.')
h('Archiver et retrouver')
add('Cliquez sur <b>Archiver</b> pour conserver un dossier en consultation. Dans Projets, choisissez le filtre Archivés pour le retrouver. Utilisez Restaurer pour reprendre les modifications. Les documents importants disposent également d’une action d’archivage ; les montants des factures émises restent comptabilisés.')

# 9
start('08 / Exemple complet', 'Un dossier Restaurant Sithinem', 'Exemple pédagogique : les montants ci-dessous illustrent le fonctionnement. Ils ne constituent pas vos tarifs actuels et ne créent aucune donnée dans Codenyr. Pour simplifier, cet exemple utilise une TVA désactivée.')
table(['Prestations retenues', 'Montant'], [
    ['Site vitrine', '900,00 €'], ['Réservation', '300,00 €'], ['SEO', '100,00 €'],
    ['Mise en ligne', '200,00 €'], ['<b>Total initial</b>', '<b>1 500,00 €</b>'],
    ['Maintenance mensuelle, séparée du total initial', '39,00 € / mois'],
], [WIDTH-123,123])
step(1, 'Créer le dossier', 'Sélectionnez ou créez le client Restaurant Sithinem. Créez le projet « Création du site Restaurant Sithinem », puis renseignez les informations et dates connues.')
step(2, 'Préparer le devis', 'Ajoutez les prestations de l’exemple et la maintenance mensuelle. Choisissez un acompte de 40 % : <b>600 €</b> d’acompte et <b>900 €</b> de solde prévisionnel. Enregistrez et vérifiez le PDF.')
step(3, 'Enregistrer l’accord et les documents', 'Marquez le devis envoyé, transmettez le PDF, puis enregistrez Accepté après le retour du client. Créez les CGV et le contrat, transmettez-les, puis importez les versions signées depuis leurs originaux.')
step(4, 'Facturer et régler l’acompte', 'Créez la facture d’acompte de <b>600 €</b>, contrôlez-la et émettez-la. Une fois le virement reçu, enregistrez le paiement de 600 €. Le dossier affiche Facturé 600 €, Payé 600 €, Reste dû 0 € ; il reste 900 € à facturer sur le devis.')
step(5, 'Livrer et solder le projet', 'Mettez à jour le statut opérationnel selon l’avancement. Créez puis émettez la facture de solde de <b>900 €</b>. Après son règlement, le dossier affiche Facturé 1 500 €, Payé 1 500 €, Reste dû 0 €.')
step(6, 'Gérer la suite', 'Si la maintenance est retenue, créez sa fiche et son contrat, importez la signature et activez-la. Facturez chaque période à part. Archivez le dossier lorsque vous voulez le conserver en consultation, ou laissez-le en suivi de maintenance selon votre organisation.')

# 10
start('09 / Aide et habitudes', 'Les réponses aux questions courantes', 'Ces repères permettent de comprendre les comportements qui peuvent surprendre lors des premières utilisations.')
table(['Situation', 'Explication ou action'], [
    ['L’URL bloque la création du projet.', 'Laissez le champ vide si le site n’existe pas encore, ou saisissez un domaine tel que www.exemple.fr. Une erreur de saisie est maintenant expliquée en français.'],
    ['Le PDF ne montre pas ma dernière modification.', 'Enregistrez d’abord le brouillon, puis générez le PDF enregistré. Les changements encore présents uniquement dans le formulaire ne sont pas repris.'],
    ['Je ne peux plus modifier un devis envoyé.', 'Dupliquez-le pour préparer une nouvelle proposition. Le document envoyé reste conservé.'],
    ['Je ne peux pas accepter un devis en brouillon.', 'Marquez-le envoyé avant d’enregistrer son acceptation. Cette action conserve le document.'],
    ['Une facture depuis un devis rouvre un brouillon.', 'Codenyr réutilise le brouillon existant pour ce devis. Une émission doit être terminée avant de préparer la facture suivante.'],
    ['La facture émise est verrouillée.', 'Son contenu et son numéro sont conservés. L’archivage reste possible. La gestion des avoirs n’est pas encore disponible.'],
    ['Le paiement est refusé.', 'Sélectionnez une facture émise du bon projet et un montant positif inférieur ou égal au reste dû. Vérifiez aussi la date.'],
    ['Un montant de contrat indique « À préciser ».', 'Pour les variables de prix, délai et conditions, choisissez un devis accepté associé au même projet avant d’enregistrer le document.'],
    ['La maintenance active ne produit pas de facture.', 'Les factures récurrentes automatiques ne sont pas encore proposées. Créez une facture pour chaque période à facturer.'],
], [174,WIDTH-174])
h('Votre routine en quatre gestes')
add('<b>1.</b> Regardez les Actions requises du dashboard.<br/><b>2.</b> Ouvrez le projet concerné et mettez à jour son statut.<br/><b>3.</b> Ajoutez les documents reçus et les paiements réellement encaissés.<br/><b>4.</b> Vérifiez la checklist, puis utilisez Recherche pour retrouver une pièce ou un numéro.')
add('Accès local à l’administration : <link href="https://codenyr.test/admin" color="#2563EB">https://codenyr.test/admin</link><br/>Ce guide décrit les fonctions présentes dans le dépôt au 1er octobre 2026, y compris la correction récente du champ URL.', 'small')

class NumberedCanvas(canvas.Canvas):
    def __init__(self, *args, **kwargs):
        canvas.Canvas.__init__(self,*args,**kwargs)
        self.saved=[]
    def showPage(self):
        self.saved.append(dict(self.__dict__))
        self._startPage()
    def save(self):
        total=len(self.saved)
        for state in self.saved:
            self.__dict__.update(state)
            self.setStrokeColor(BORDER)
            self.line(44,41,PAGE_W-44,41)
            self.setFillColor(MUTED)
            self.setFont('Guide',8)
            self.drawString(44,27,'CODENYR | Guide de la gestion commerciale')
            self.drawRightString(PAGE_W-44,27,f'{self._pageNumber} / {total}')
            canvas.Canvas.showPage(self)
        canvas.Canvas.save(self)

def header(c, doc):
    if doc.page > 1:
        c.saveState()
        c.setFillColor(BLUE)
        c.rect(44,PAGE_H-35,28,3,fill=1,stroke=0)
        c.setFillColor(MUTED)
        c.setFont('Guide',8)
        c.drawRightString(PAGE_W-44,PAGE_H-34,'MODE D’EMPLOI / OCTOBRE 2026')
        c.restoreState()

doc = SimpleDocTemplate(str(OUTPUT), pagesize=(PAGE_W,PAGE_H), leftMargin=44,rightMargin=44,topMargin=57,bottomMargin=56, title='Codenyr - Guide de la gestion commerciale', author='Codenyr', subject='Utilisation des nouveaux modules commerciaux')
doc.build(story, onFirstPage=header, onLaterPages=header, canvasmaker=NumberedCanvas)
reader=PdfReader(str(OUTPUT))
if len(reader.pages) != 10:
    raise RuntimeError(f'Expected 10 pages, got {len(reader.pages)}')
with pdfplumber.open(str(OUTPUT)) as pdf:
    for index,page in enumerate(pdf.pages,1):
        words=page.extract_words()
        if not words:
            raise RuntimeError(f'Empty page {index}')
        outside=[w['text'] for w in words if w['x0'] < 39 or w['x1'] > PAGE_W-39 or w['top'] < 19 or w['bottom'] > PAGE_H-19]
        if outside:
            raise RuntimeError(f'Text outside safe area on page {index}: {outside}')
        print(f'Page {index}: {len(words)} words')
print(f'PDF generated: {OUTPUT}')
