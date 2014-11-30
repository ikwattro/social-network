<?php

namespace Ikwattro\SocialNetwork\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class WebController
{

    public function home(Application $application, Request $request)
    {
        $neo = $application['neo'];
        $q = 'MATCH (users:User) RETURN users';
        $result = $neo->sendCypherQuery($q)->getResult();

        $users = $result->getNodes();

        return $application['twig']->render('index.html.twig', array(
            'users' => $users
        ));
    }

    public function showUser(Application $application, Request $request, $login)
    {
        $neo = $application['neo'];
        $q = 'MATCH (user:User) WHERE user.login = {login}
         OPTIONAL MATCH (user)-[:FOLLOWS]->(f)
         OPTIONAL MATCH (f)-[:FOLLOWS]->(fof)
         WHERE NOT user.login = fof.login
         AND NOT (user)-[:FOLLOWS]->(fof)
         RETURN user, collect(distinct f) as followed, collect(distinct fof) as suggestions';
        $p = ['login' => $login];
        $result = $neo->sendCypherQuery($q, $p)->getResult();

        $user = $result->get('user');
        $followed = $result->get('followed');
        $suggestions = $result->get('suggestions');

        if (null === $user) {
            $application->abort(404, 'The user $login was not found');
        }

        return $application['twig']->render('show_user.html.twig', array(
            'user' => $user,
            'followed' => $followed,
            'suggestions' => $suggestions
        ));
    }

    public function createRelationship(Application $application, Request $request)
    {
        $neo = $application['neo'];
        $user = $request->get('user');
        $toFollow = $request->get('to_follow');

        $q = 'MATCH (user:User {login: {login}}), (target:User {login:{target}})
        CREATE (user)-[:FOLLOWS]->(target)';
        $p = ['login' => $user, 'target' => $toFollow];
        $neo->sendCypherQuery($q, $p);

        $redirectRoute = $application['url_generator']->generate('show_user', array('login' => $user));

        return $application->redirect($redirectRoute);
    }

    public function removeRelationship(Application $application, Request $request)
    {
        $neo = $application['neo'];
        $user = $request->get('login');
        $toRemove = $request->get('to_remove');

        $q = 'MATCH (user:User {login: {login}} ), (badfriend:User {login: {target}} )
        OPTIONAL MATCH (user)-[r:FOLLOWS]->(badfriend)
        DELETE r';
        $p = ['login' => $user, 'target' => $toRemove];
        $neo->sendCypherQuery($q, $p);

        $redirectRoute = $application['url_generator']->generate('show_user', array('login' => $user));

        return $application->redirect($redirectRoute);
    }
}