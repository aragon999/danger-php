<?php
declare(strict_types=1);

namespace Danger\Struct\Gitlab;

use Danger\Struct\Comment;
use Danger\Struct\CommentCollection;
use Danger\Struct\Commit;
use Danger\Struct\CommitCollection;
use Danger\Struct\FileCollection;
use Gitlab\Client;
use Gitlab\ResultPager;

class PullRequest extends \Danger\Struct\PullRequest
{
    private ?CommitCollection $commits = null;
    private ?FileCollection $files = null;
    private ?CommentCollection $comments = null;

    public function __construct(private Client $client, private string $latestSha)
    {
    }

    public function getCommits(): CommitCollection
    {
        if ($this->commits) {
            return $this->commits;
        }

        $this->rawCommits = $this->client->mergeRequests()->commits($this->projectIdentifier, (int) $this->id);

        $collection = new CommitCollection();

        foreach ($this->rawCommits as $rawGithubCommit) {
            $commit = new Commit();
            $commit->sha = $rawGithubCommit['id'];
            $commit->createdAt = new \DateTime($rawGithubCommit['committed_date']);
            $commit->message = $rawGithubCommit['message'];
            $commit->author = $rawGithubCommit['author_name'];
            $commit->authorEmail = $rawGithubCommit['author_email'];
            $commit->verified = false;

            $collection->add($commit);
        }

        return $this->commits = $collection;
    }

    public function getFiles(): FileCollection
    {
        if ($this->files) {
            return $this->files;
        }

        $this->rawFiles = $this->client->mergeRequests()->changes($this->projectIdentifier, (int) $this->id);

        $collection = new FileCollection();

        foreach ($this->rawFiles['changes'] as $rawGithubFile) {
            $file = new File($this->client, $this->projectIdentifier, $rawGithubFile['new_path'], $this->latestSha);
            $file->name = $rawGithubFile['new_path'];
            $file->status = $rawGithubFile['new_file'] ? File::STATUS_ADDED : ($rawGithubFile['deleted_file'] ? File::STATUS_REMOVED : File::STATUS_MODIFIED);
            $file->additions = 0;
            $file->deletions = 0;
            $file->changes = $file->additions + $file->deletions;

            $collection->set($file->name, $file);
        }

        return $this->files = $collection;
    }

    public function getComments(): CommentCollection
    {
        if ($this->comments !== null) {
            return $this->comments;
        }

        $this->comments = new CommentCollection();

        $pager = new ResultPager($this->client);
        $comments = $pager->fetchAll($this->client->mergeRequests(), 'showNotes', [$this->projectIdentifier, (int) $this->id]);

        foreach ($comments as $commentArray) {
            if ($commentArray['system']) {
                continue;
            }

            $comment = new Comment();
            $comment->author = $commentArray['author']['username'];
            $comment->body = $commentArray['body'];
            $comment->createdAt = new \DateTime($commentArray['created_at']);
            $comment->updatedAt = new \DateTime($commentArray['updated_at']);

            $this->comments->add($comment);
        }

        return $this->comments;
    }
}
