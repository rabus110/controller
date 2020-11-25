<?php

namespace App\Controller;

use App\Entity\Project;
use App\Services\Helper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;

class ProjectController extends AbstractController
{
    private $entityManager;
    private $helper;

    public function __construct(
        EntityManagerInterface $entityManager,
        Helper $helper
    ) {
        $this->entityManager = $entityManager;
        $this->helper = $helper;
    }

    /**
     * @Route("/api/v1/projects/{id}", name="get_project", methods={"GET"})
     * @param $id
     * @return JsonResponse
     */
    public function getProjects($id): JsonResponse
    {
        $code = 200;
        $message = '';

        $project = $this->entityManager->getRepository(Project::class)->find($id);

        if (!$project || !$project->getIsActive()) {
            $code = 401;
            $message = 'Project doesn\'t exists.';
        }

        return $this->helper->sendResponse($code, $message, $project, 'json');
    }

    /**
     * @Route("/api/v1/projects", name="get_all_projects", methods={"GET"})
     * @return JsonResponse
     */
    public function getAllProjects(): JsonResponse
    {
        $code = 200;
        $message = '';

        $projects = $this->entityManager->getRepository(Project::class)->findBy(['is_active' => true]);

        if (!$projects) {
            $code = 401;
            $message = 'No projects found.';
        }

        return $this->helper->sendResponse($code, $message, $projects, 'json');
    }

    /**
     * @Route("/api/v1/projects", name="add_or_update_project", methods={"POST"})
     * @param $request
     * @return JsonResponse
     */
    public function sendProject(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent());
        $valid = $this->helper->validator($data,
            new Assert\Collection([
                'id' => [new Assert\Type('integer'), new Assert\Required()],
                'name' => [new Assert\Type('string'), new Assert\NotBlank()]
            ])
        );
        if (count($valid) > 0) {
            $code = 401;
            $message = $valid[0]->getMessage();

            return $this->helper->sendResponse($code, $message, $valid[0]->getParameters(), 'json');
        }

        $code = 200;
        $message = '';
        $project = [];

        if ($data) {
            $project = $this->addOrUpdateProject($data->id, $data->name, $this->getUser());

            if (!$project) {
                $code = 401;
                $message = 'Can\'t add or update project with given id.';
            }
        } else {
            $code = 401;
            $message = 'No data provided.';
        }

        return $this->helper->sendResponse($code, $message, $project, 'json');
    }

    /**
     * @Route("/api/v1/projects/{id}", name="delete_project", methods={"DELETE"})
     * @return JsonResponse
     */
    public function deleteProject($id): JsonResponse
    {
        $code = 200;
        $message = '';

        $project = $this->entityManager->getRepository(Project::class)->find($id);

        if ($project && $project->getIsActive()) {
            $project->setIsActive(false);
            $this->entityManager->persist($project);
            $this->entityManager->flush();

            $message = 'Deleted project.';
        } else {
            $code = 401;
            $message = 'Project doesn\'t exists.';
        }

        return $this->helper->sendResponse($code, $message, null, 'json');
    }

    /**
     * @param $id
     * @param $name
     * @param $user
     * @return Project
     */
    private function addOrUpdateProject($id, $name, $user)
    {
        $project = $this->entityManager->getRepository(Project::class)->find($id);

        if (!$project || !$project->getIsActive()) {
            $project = new Project();
            $project->setCreatedAt(new \DateTime('now'))
                ->setCreatedBy($user);
        }

        $project->setName($name)
            ->setUpdatedAt(new \DateTime('now'))
            ->setUpdatedBy($user)
            ->setIsActive(true);

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }
}
