static-ish
==========

(c) 2009 Jason Frame :: 
  [jason@onehackoranother.com](mailto:jason@onehackoranother.com) :: 
  [@jaz303](http://twitter.com/jaz303)
  
Released under the MIT License

Synopsis
--------

static-ish is a plain text content library for PHP comprising a simple blog engine and support for pages composed of metadata and a list typed blocks.

Really Quick Example
--------------------

Pages are marked up thusly:

    title: My F1rst p0st
    published_at: 2009-12-11T22:23
    tags: ["foo", "bar"]
    
    --- markdown
    
    Summary
    -------
    
    Lorem ipsum dolor sit amet, consectetur adipisicing elit,
    sed do eiusmod tempor incididunt ut labore et dolore magna
    aliqua. Ut enim ad minim veniam, quis nostrud exercitation
    ullamco laboris nisi ut aliquip ex ea commodo consequat.
    Duis aute irure dolor in reprehenderit in voluptate velit
    esse cillum dolore eu fugiat nulla pariatur. Excepteur sint
    occaecat cupidatat non proident, sunt in culpa qui officia
    deserunt mollit anim id est laborum.
    
    --- code language: javascript highlight: 1,2,3
    
    // fibonacci series
    (function(n) {
      if (n < 2) {
        return n;
      } else {
        return arguments.callee(n - 2) + arguments.callee(n - 1);
      }
    })(10);
    
    --- pagebreak
    
    --- markdown
    
    Duis aute irure dolor in reprehenderit in voluptate velit
    esse cillum dolore eu fugiat nulla pariatur. Excepteur sint
    occaecat cupidatat non proident, sunt in culpa qui officia
    deserunt mollit anim id est laborum.
    
Single pages are accessed like so:

    $page = Page::load($path_to_page);
    echo $page->title; // retrieve "title" from metadata
    echo $page->block_html(); // render block content as HTML

And to get your blog on:

    $blog = new Blog($path_to_blog_root);
    $blog->get_page(2, 10); // 2nd page, 10 posts per page
    $blog->get_monthly_archive_counts();
    $blog->get_monthly_archive(2009, 2); // array of posts for Feb 2009
    $blog->get_post('2009/02/my-first-post'); // load a single post
    $blog->get_feed(10); // RSS feed containing 10 most recent items
    
Documentation
-------------

[Read the static-ish documentation at onehackoranother.com](http://onehackoranother.com/projects/php/static-ish)