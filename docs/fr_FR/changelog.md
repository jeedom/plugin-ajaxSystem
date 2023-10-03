# Changelog Ajax Systems

>**IMPORTANT**
>
>Pour rappel s'il n'y a pas d'information sur la mise à jour, c'est que celle-ci concerne uniquement de la mise à jour de documentation, de traduction ou de texte

# 03/10/2023
- Ajout d'un nouveau statut d'alarme en cas d'armement forcé (par exemple lorsqu'un équipement est en erreur mais qu'on force la mise en service de l'alarme)
  Ce nouveau statut est disponible sur la commande statut du Hub, et a comme valeur technique "FORCED_ARM". Un logo avec un bouclier partiellement rempli s'affiche désormais sur le widget dans ce mode pour indiquer clairement que l'alarme est en service mais avec des défauts potentiels
- Révision du mécanisme de récupération des mises à jour de commande pour permettre une plus grande flexibilité. Dans un avenir proche, celà devrait permettre de rajouter     
  davantage d'informations sur les équipements. En fonction du temps et du matériel disponible pour les tests
- Retrait de la possibilité d'ajuster manuellement les Logical Id sur les commandes d'équipement
- Retrait de la possibilité d'ajouter ou de supprimer manuellement des commandes d'équipement
- Préparatifs en vue de la mise en place d'un mécanisme de mise à niveau des commandes des équipements lors de la mise à jour du plugin. Celà permettra de supprimer les      commandes obsoletes mais également de rajouter de nouvelles commandes sans impacter l'utilisateur final. (Cette partie est actuellement en dévelopement)
- Mise à jour de la documentation

# 06/06/2023

- Ajout hub fibra

# 23/08/2022

- Ajout de la gestion des groupes
- Amélioration du support du multi transmitter

# 09/06/2022

- Suppression du refresh automatique des informations toute les heures pour limiter le nombre d'appels à Ajax et prevenir du dépassement de quota

# 21/02/2021

- Correction d'un bug avec le protocole SIA

# 05/01/2021

- Correction d'un soucis pour Socket

# 04/01/2022

- Optimisation de l'installation des dépendances
- Correction de la gestion des couleurs de l'équipements
- Ajout du Dual Curtain Outdoor
- Ajout Wall Switch

# 11/12/2021

- Gestion de la couleur des modules pour afficher la bonne image (necessite de refaire une synchronisation)
- Correction d'un soucis sur les entrées externe des DoorProtect (une supression des équipements et resynchronisation est necessaire)
- Correction d'un soucis sur le démon SIA
- Mise à jour de la documentation

# 02/12/2021

- Ajout des commandes on/off pour les relais
- Ajout d'un démon SIA pour la recuperation local de certaine informations (bien lire la documentation pour la configuration)
- Ajout d'équipements compatibles

# 19/08/2021

- Decalage aléatoire du cron de refresh pour essayer de corriger le soucis "You have exceeded the limit in 100 requests per minute"
