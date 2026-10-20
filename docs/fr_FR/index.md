# Ajax Système

## Configuration

>**IMPORTANT**
>

Pour avoir une remontée en temps réel il faut ABSOLUMENT que votre Jeedom soit accessible de l'extérieure (URL d'accès externe utilisée) en HTTPS avec un certificat valide

La configuration du plugin est très simple et se déroule en 2 étapes : 

- mise en place du lien entre votre jeedom et votre alarme,
- ajout d'un partage par mail pour la remontée des événements.

>**IMPORTANT**
>
>Un point important, Ajax ne remonte pas d'alerte globale lors d'un déclenchement d'alarme mais remonte le statut sur le détecteur qui a déclenché l'alarme (commande événements).

## Compatibilité

Vous pouvez trouver [ici](https://compatibility.jeedom.com/index.php?v=d&p=home&plugin=ajaxSystem) la liste des modules compatibles avec le plugin.

### Configuration du lien 

Pour la mise en place du lien entre votre Jeedom et votre alarme Ajax, il faut aller dans "Plugin" -> "Gestion de plugin" -> "Ajax System" puis cliquer sur "Se connecter", là vous rentrez vos identifiants Ajax et cliquez sur "Valider".

>**IMPORTANT**
>
>Si vous avez un compte pro il ne faut surtout pas l'utiliser la, il faut absolument utiliser un compte utilisateur simple.

>**NOTE**
>
> Jeedom ne sauvegarde absolument pas vos identifiants Ajax : ils sont juste une utilisés pour la première requête à Ajax et avoir les jetons d'accès (_access token_) et de rafraîchissement (_refresh token_). Le refresh token permet de récupérer un nouveau token d'accès qui a une durée de vie de quelques heures seulement.

>**NOTE**
>
> Une fois le lien fait, toutes les requêtes passent par notre cloud Jeedom mais à aucun moment le cloud ne stocke votre token d'accès, il n'est donc pas possible avec seulement le cloud jeedom d'agir sur votre alarme. Pour toute action, sur celle-ci il faut absolument la combinaison du token d'accès de votre Jeedom et d'une clef connue uniquement de notre cloud.

### Configuration de la remontée d'événements

Il faut depuis l'application Ajax :

- aller sur le hub puis
- dans paramètres (petite roue crantée en haut à droite) aller sur utilisateur et
- là rajouter l'utilisateur : `ajax@jeedom.com`.

>**NOTE**
>
>L'invitation reste et restera toujours en attente c'est normal.

## Équipement 

Une fois la configuration sur "Plugin" -> "Gestion de plugin" -> "Ajax System", il vous suffit de faire synchroniser, Jeedom va automatiquement créer tous les équipements Ajax reliés à votre compte Ajax. 

### Détecteur de mouvement

Petite spécificité pour le détecteur de mouvement, celui-ci ne remonte pas la détection de mouvement en permanence. En effet il ne le remonte que lorsque l'alarme est active et par la commande Événement.

### Détecteur d'ouverture

Pour lui pas de soucis, vous avez l'état en temps réel de l'information de fenêtre/porte ouverte/fermée.
