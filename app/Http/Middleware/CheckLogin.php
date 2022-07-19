<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use DB;

class CheckLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
         if($request->id){
            $check = DB::table('users')
            ->select('email')
            ->where('id','=',$request->id)
            ->count();
            if ($check == 0) {
            return redirect('/login');   
            }else{
            return $next($request);
            }
        }else{
            return redirect('/login');
        }
    }
}
