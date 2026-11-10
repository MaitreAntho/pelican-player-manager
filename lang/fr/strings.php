<?php

return [
    'title' => 'Joueurs',
    'nobody' => 'Personne n’est connecté',
    'disabled_title' => 'Player Manager est désactivé sur ce serveur',
    'disabled_help' => 'Ce serveur est réglé comme n’étant pas un serveur de jeu. Choisissez son jeu, ou Automatique, dans les réglages (en haut à droite) pour le réactiver.',
    'count_only' => ':count joueurs connectés',
    'count_only_help' => 'Ce jeu indique le nombre de joueurs connectés, mais pas leurs noms.',
    'no_output' => '(pas de réponse)',
    'output' => 'Réponse du serveur',

    'columns' => ['name' => 'Joueur', 'id' => 'Identifiant', 'score' => 'Score', 'time' => 'Temps de jeu', 'ping' => 'Ping'],

    'actions' => [
        'kick' => 'Expulser', 'ban' => 'Bannir', 'unban' => 'Débannir', 'say' => 'Message à tous',
        'whitelist' => 'Ajouter à la liste blanche', 'unwhitelist' => 'Retirer de la liste blanche', 'op' => 'Rendre opérateur', 'deop' => 'Retirer opérateur',
        'list' => 'Lister par la console', 'list_help' => 'Lance une fois la commande de liste du jeu, pour voir les noms que sa requête ne donne pas.',
        'refresh' => 'Actualiser', 'reason' => 'Motif (facultatif)', 'message' => 'Message', 'send' => 'Envoyer',
        'command' => 'Lancer une commande', 'run' => 'Lancer',
        'command_rcon' => 'Envoyée au jeu par sa console à distance. La réponse s’affiche sous la liste.',
        'command_console' => 'Envoyée par la console du serveur, comme si elle était tapée dans la page Console. La réponse s’affiche sous la liste.',
        'unban_target' => 'Joueur', 'unban_help' => 'Le nom ou l’identifiant avec lequel le joueur a été banni.',
        'ban_help' => 'Le joueur est retiré et ne peut plus revenir tant qu’il n’est pas débanni.',
        'kick_heading' => 'Expulser :name', 'ban_heading' => 'Bannir :name', 'whitelist_heading' => 'Ajouter :name à la liste blanche',
        'unwhitelist_heading' => 'Retirer :name de la liste blanche', 'op_heading' => 'Rendre :name opérateur', 'deop_heading' => 'Retirer :name des opérateurs',
    ],

    'done' => [
        'kick' => ':name a été expulsé', 'ban' => ':name a été banni', 'unban' => ':name a été débanni', 'say' => 'Message envoyé',
        'whitelist' => ':name a été ajouté à la liste blanche', 'unwhitelist' => ':name a été retiré de la liste blanche',
        'op' => ':name est maintenant opérateur', 'deop' => ':name n’est plus opérateur',
        'via_console' => 'Envoyé par la console du serveur.',
        'via_console_unreachable' => 'Envoyé par la console du serveur : la console à distance n’a pas répondu au panel.',
        'via_console_auth' => 'Envoyé par la console du serveur : la console à distance a refusé le mot de passe (à vérifier dans les réglages).',
    ],

    'errors' => [
        'no_source_title' => 'Impossible de lister les joueurs',
        'no_source' => 'Ce jeu n’a pas de requête et aucune console à distance n’est configurée. Indiquez le mot de passe de la console à distance dans les réglages (en haut à droite) pour lister les joueurs par ce biais.',
        'auth_title' => 'Mot de passe refusé',
        'auth' => 'Le jeu a refusé le mot de passe de la console à distance. Vérifiez-le dans les réglages (en haut à droite).',
        'unreachable_title' => 'Le serveur ne répond pas',
        'unreachable' => 'Le serveur est arrêté, encore en train de démarrer, ou son port de requête est fermé.',
        'action_failed' => 'La commande n’a pas pu être envoyée',
    ],

    'hints' => [
        'bedrock' => 'Les serveurs Bedrock récents utilisent transport=nethernet, qui ne répond pas aux requêtes : mettez transport=raknet dans server.properties pour afficher le nombre de joueurs.',
        'minecraft' => 'Pour voir le nom de tous les joueurs, mettez enable-query=true dans server.properties ; sinon 12 au plus s’affichent.',
        'fivem' => 'Les expulsions et les messages passent par la console du serveur.',
        'palworld' => 'Palworld liste ses joueurs par son RCON : activez RCONEnabled=True et un mot de passe admin.',
        'battleye' => 'Expulsions, bans et messages passent par BattlEye RCon : définissez un port et un mot de passe RCon.',
    ],

    'settings_title' => 'Réglages des joueurs',
    'settings' => [
        'detected' => 'Détecté',
        'game' => 'Jeu',
        'auto' => 'Automatique',
        'none' => 'Pas un serveur de jeu',
        'query_port' => 'Port de requête',
        'rcon_port' => 'Port de la console à distance',
        'rcon_password' => 'Mot de passe de la console à distance',
        'password_kept' => 'Enregistré (laisser vide pour le garder)',
        'rcon_help' => 'Seulement si le plugin ne le trouve pas dans les variables de démarrage ou les fichiers de configuration. Stocké chiffré.',
        'forget_password' => 'Oublier le mot de passe enregistré',

        'host' => 'Adresse de requête',
        'host_help' => 'Adresse utilisée par le panel pour joindre les serveurs de jeu. Vide : l’IP de l’allocation, ou l’adresse du node quand l’allocation écoute sur 0.0.0.0.',
        'refresh' => 'Actualiser toutes les',
        'refresh_help' => 'Fréquence de mise à jour de la liste des joueurs sur la page.',
        'history' => 'Garder l’historique des joueurs',
        'history_help' => 'Enregistre qui a joué et le pic du jour, une fois par minute, uniquement par requête (rien n’est écrit dans les consoles des serveurs).',
        'retention' => 'Garder l’historique',
        'days' => 'jours',
        'saved' => 'Réglages enregistrés',
    ],

    'describe' => [
        'query' => 'joueurs par :protocol sur :address',
        'no_query' => 'pas de protocole de requête',
        'rcon' => 'commandes par :protocol sur :address',
        'console' => 'commandes par la console du serveur',
    ],

    'protocols' => [
        'a2s' => 'requête Steam', 'minecraft' => 'requête Minecraft', 'bedrock' => 'ping Bedrock', 'fivem' => 'API FiveM',
        'quake3' => 'protocole Quake 3', 'teeworlds' => 'requête Teeworlds', 'satisfactory' => 'API Satisfactory', 'samp' => 'requête SA-MP',
        'ase' => 'requête ASE', 'assetto' => 'API Assetto Corsa', 'source' => 'RCON', 'goldsrc' => 'RCON GoldSrc', 'telnet' => 'telnet',
        'webrcon' => 'WebRCON', 'battleye' => 'BattlEye RCon', 'tshock' => 'REST TShock', 'palworld' => 'REST Palworld', 'console' => 'console',
    ],

    'summary' => [
        'players' => ':count joueur connecté|:count joueurs connectés',
        'players_max' => ':count / :max joueurs',
        'map' => 'carte :map',
        'via' => 'par :protocol',
        'peak_today' => 'pic du jour : :count',
    ],

    'history' => [
        'peaks' => 'Pic du jour',
        'peaks_help' => 'Le plus de joueurs connectés en même temps, sur 14 jours.',
        'today' => 'aujourd’hui',
        'recent' => 'Vus récemment',
        'recent_help' => 'Les derniers joueurs vus sur ce serveur.',
        'online' => 'en ligne',
        'empty' => 'Personne pour l’instant. L’historique se remplit à mesure que des joueurs se connectent.',
    ],

    'permissions' => [
        'players_title' => 'Joueurs',
        'players_desc' => 'Permissions de la page Joueurs : la liste des joueurs connectés et les actions sur eux.',
        'players_read' => 'Voir qui est connecté et l’historique des joueurs.',
        'players_manage' => 'Expulser, bannir, débannir, envoyer un message, liste blanche et opérateurs.',
        'players_command' => 'Lancer n’importe quelle commande sur le serveur depuis la page Joueurs.',
        'players_configure' => 'Changer le jeu, les ports et le mot de passe de console à distance utilisés par la page Joueurs.',
    ],

    'admin' => [
        'title' => 'Player Manager',
        'subheading' => 'Le jeu reconnu pour chaque egg. La page Joueurs apparaît sur les serveurs des eggs reconnus.',
        'egg' => 'Egg', 'servers' => 'Serveurs', 'game' => 'Jeu', 'not_supported' => 'Non pris en charge', 'manual' => 'Choisi à la main',
        'players' => 'Liste des joueurs', 'actions' => 'Actions', 'filter' => 'Eggs', 'filter_used' => 'Avec des serveurs', 'change' => 'Changer le jeu',
    ],
];
