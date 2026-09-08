<?php
/**
 * Français (fr_FR) — catalogue de traduction. LA LANGUE SOURCE EST L’ANGLAIS.
 *
 * Vouvoiement, conformément à la traduction du cœur de WordPress en fr_FR.
 * Espaces insécables avant « : » « ! » « ? » et à l’intérieur des guillemets,
 * comme le veut la typographie française.
 *
 * @package no404
 */

return array(
	'Settings' => 'Réglages',
	'no404 – Auto 404 Redirect' => 'no404 – Redirection 404 automatique',
	'no404' => 'no404',
	'Connection' => 'Connexion',
	'Behaviour' => 'Comportement',
	'no404 address' => 'Adresse no404',
	'API key' => 'Clé d’API',
	'Redirecting' => 'Redirection',
	'Permanent redirects' => 'Redirections permanentes',
	'Cache lifetime' => 'Durée du cache',
	'Timeout' => 'Délai d’attente',
	'Excluded paths' => 'Chemins exclus',
	'Get your API key from the site settings page in your no404 dashboard. The key is used server-side only and never appears in your site\'s source code.' => 'Récupérez votre clé d’API sur la page des réglages du site, dans votre tableau de bord no404. La clé est utilisée uniquement côté serveur et n’apparaît jamais dans le code source de votre site.',
	'Caching stops bots that hit the same dead URL over and over from burning through your monthly event quota. A shorter lifetime means higher quota usage.' => 'Le cache empêche les robots qui sollicitent sans cesse la même URL morte d’épuiser votre quota mensuel d’évènements. Plus la durée est courte, plus le quota est consommé.',
	'You should not need to touch this — the default, %s, is the right address. Change it only if you host no404 on your own server. Leave the field empty to restore the default.' => 'Vous ne devriez pas avoir à modifier ce réglage : la valeur par défaut, %s, est la bonne adresse. Ne la changez que si vous hébergez no404 sur votre propre serveur. Laissez le champ vide pour rétablir la valeur par défaut.',
	'Paste your key' => 'Collez votre clé',
	'Saved key: %s — leave this field empty to keep it.' => 'Clé enregistrée : %s — laissez ce champ vide pour la conserver.',
	'Apply no404 redirects on pages that are not found' => 'Appliquer les redirections no404 sur les pages introuvables',
	'Send every match as a 301 (permanent)' => 'Envoyer chaque correspondance en 301 (permanente)',
	'By default only manually defined redirects and high-scoring matches are sent as 301; speculative matches are sent as 302. A 301 is cached permanently by browsers and cannot be taken back — tick this box only if you are confident your catalogue is complete.' => 'Par défaut, seules les redirections définies manuellement et les correspondances à score élevé sont envoyées en 301 ; les correspondances incertaines sont envoyées en 302. Une 301 est mise en cache définitivement par les navigateurs et ne peut pas être annulée : ne cochez cette case que si vous êtes certain que votre catalogue est complet.',
	'seconds' => 'secondes',
	'Default 3600 (1 hour). Minimum 60, maximum 604800 (7 days).' => 'Valeur par défaut : 3600 (1 heure). Minimum 60, maximum 604800 (7 jours).',
	'milliseconds' => 'millisecondes',
	'If no404 does not answer within this time the request is dropped and your site shows its own 404 page. Visitors are never left waiting.' => 'Si no404 ne répond pas dans ce délai, la requête est abandonnée et votre site affiche sa propre page 404. Les visiteurs ne sont jamais laissés en attente.',
	'One path prefix per line. URLs starting with these prefixes are never sent to no404. Static files (.css, .js, .png …) and paths such as /wp-admin and /wp-json are excluded automatically.' => 'Un préfixe de chemin par ligne. Les URL commençant par ces préfixes ne sont jamais envoyées à no404. Les fichiers statiques (.css, .js, .png …) et les chemins comme /wp-admin et /wp-json sont exclus automatiquement.',
	'Testing…' => 'Test en cours…',
	'The test could not be completed. Reload the page and try again.' => 'Le test n’a pas pu aboutir. Rechargez la page et réessayez.',
	'The plugin is not running yet: no API key has been entered.' => 'L’extension n’est pas encore active : aucune clé d’API n’a été saisie.',
	'Redirecting is switched off. 404 pages are shown as they are.' => 'La redirection est désactivée. Les pages 404 sont affichées telles quelles.',
	'Active. Pages that are not found are redirected server-side.' => 'Active. Les pages introuvables sont redirigées côté serveur.',
	'Connection test' => 'Test de connexion',
	'Sends a real request to no404 using your saved settings. Save your changes first.' => 'Envoie une vraie requête à no404 avec vos réglages enregistrés. Enregistrez d’abord vos modifications.',
	'Path to test' => 'Chemin à tester',
	'Test the connection' => 'Tester la connexion',
	'You do not have permission to do this.' => 'Vous n’avez pas l’autorisation d’effectuer cette action.',
	'The client could not be initialised.' => 'Le client n’a pas pu être initialisé.',
	'Connection succeeded. Suggested target for this path: %1$s (source: %2$s, score: %3$s).' => 'Connexion réussie. Cible suggérée pour ce chemin : %1$s (source : %2$s, score : %3$s).',
	'Connection succeeded. Your API key is valid; no match was found for this test path, which is what we expect.' => 'Connexion réussie. Votre clé d’API est valide ; aucune correspondance n’a été trouvée pour ce chemin de test, ce qui est le comportement attendu.',
	'No API key has been entered. Save your key and try again.' => 'Aucune clé d’API n’a été saisie. Enregistrez votre clé et réessayez.',
	'The no404 address is empty.' => 'L’adresse no404 est vide.',
	'Invalid API key (404). Copy the key from the site settings page in your no404 dashboard; if you rotated the key recently, enter the new one here as well.' => 'Clé d’API invalide (404). Copiez la clé depuis la page des réglages du site, dans votre tableau de bord no404 ; si vous l’avez renouvelée récemment, saisissez également la nouvelle ici.',
	'Access denied (403): %s. Your subscription may be inactive, monitoring for this site may be paused, or your account may be suspended.' => 'Accès refusé (403) : %s. Votre abonnement est peut-être inactif, la surveillance de ce site suspendue, ou votre compte désactivé.',
	'Access denied (403). Your subscription may be inactive, monitoring for this site may be paused, or your account may be suspended.' => 'Accès refusé (403). Votre abonnement est peut-être inactif, la surveillance de ce site suspendue, ou votre compte désactivé.',
	'Rate limit exceeded, or your monthly event quota is used up (429). Check your quota in the no404 dashboard; if you sent many requests in a short time, try again in a minute.' => 'Limite de requêtes atteinte, ou quota mensuel d’évènements épuisé (429). Vérifiez votre quota dans le tableau de bord no404 ; si vous avez envoyé beaucoup de requêtes en peu de temps, réessayez dans une minute.',
	'The test path is invalid (422). Enter a path that starts with "/".' => 'Le chemin de test est invalide (422). Saisissez un chemin commençant par « / ».',
	'Could not reach the no404 server: %s. Make sure your server is allowed to make outbound HTTPS requests.' => 'Impossible de joindre le serveur no404 : %s. Vérifiez que votre serveur est autorisé à effectuer des requêtes HTTPS sortantes.',
	'Could not reach the no404 server. Make sure your server is allowed to make outbound HTTPS requests.' => 'Impossible de joindre le serveur no404. Vérifiez que votre serveur est autorisé à effectuer des requêtes HTTPS sortantes.',
	'no404 hit a temporary error (5xx). Your site is unaffected; try again shortly.' => 'no404 a rencontré une erreur temporaire (5xx). Votre site n’est pas affecté ; réessayez dans un instant.',
	'Unexpected response (HTTP %d).' => 'Réponse inattendue (HTTP %d).',
	'The no404 address must be a valid http(s) URL. The previous value has been kept. Leave the field empty to restore the default.' => 'L’adresse no404 doit être une URL http(s) valide. La valeur précédente a été conservée. Laissez le champ vide pour rétablir la valeur par défaut.',
);
